<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Pdo\Transaction;

use ExtendsSoftware\ExaPHP\Integration\Pdo\Transaction\PdoTransactionManager;
use ExtendsSoftware\ExaPHP\Transaction\Exception\NestedTransactionException;
use ExtendsSoftware\ExaPHP\Transaction\Exception\TransactionStartException;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;

final class PdoTransactionManagerIntegrationTest extends TestCase
{
    /**
     * @return iterable<string, array{int}>
     */
    public static function incompatibleErrorModes(): iterable
    {
        yield 'silent' => [PDO::ERRMODE_SILENT];
        yield 'warning' => [PDO::ERRMODE_WARNING];
    }

    #[DataProvider('incompatibleErrorModes')]
    public function testRejectsIncompatibleModeBeforeStartingAndCanRecover(int $mode): void
    {
        $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => $mode]);
        $manager = new PdoTransactionManager($pdo);
        try {
            $manager->transactional(static fn() => throw new RuntimeException('Must not run'));
            $this->fail('Expected incompatible error mode rejection.');
        } catch (TransactionStartException) {
            $this->assertFalse($pdo->inTransaction());
            $this->assertSame($mode, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->assertSame('saved', $manager->transactional(static fn(): string => 'saved'));
    }

    public function testChecksErrorModeAgainAfterAnEarlierTransaction(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $manager = new PdoTransactionManager($pdo);
        $manager->transactional(static fn(): string => 'saved');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $this->expectException(TransactionStartException::class);
        $manager->transactional(static fn() => throw new RuntimeException('Must not run'));
    }

    public function testSqlFailureRollsBackEarlierWrites(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE entries (id INTEGER PRIMARY KEY)');
        try {
            new PdoTransactionManager($pdo)->transactional(static function () use ($pdo): void {
                $pdo->exec('INSERT INTO entries VALUES (1)');
                $pdo->exec('INSERT INTO entries VALUES (1)');
            });
            $this->fail('Expected constraint failure.');
        } catch (PDOException) {
            $this->assertFalse($pdo->inTransaction());
            $this->assertSame(0, $pdo->query('SELECT COUNT(*) FROM entries')->fetchColumn());
        }
    }

    public function testCommitsWritesAndReturnsTheResult(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE entries (id INTEGER)');
        $manager = new PdoTransactionManager($pdo);
        $result = $manager->transactional(static function () use ($pdo): string {
            $pdo->exec('INSERT INTO entries VALUES (1)');

            return 'saved';
        });
        $this->assertSame('saved', $result);
        $this->assertFalse($pdo->inTransaction());
        $this->assertSame(1, $pdo->query('SELECT COUNT(*) FROM entries')->fetchColumn());
    }

    public function testRollsBackEngineErrorsAndCanBeReused(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE entries (id INTEGER)');
        $manager = new PdoTransactionManager($pdo);
        $failure = new TypeError('Operation failed');
        try {
            $manager->transactional(static function () use ($pdo, $failure): void {
                $pdo->exec('INSERT INTO entries VALUES (1)');
                throw $failure;
            });
            $this->fail('Expected operation failure.');
        } catch (TypeError $exception) {
            $this->assertSame($failure, $exception);
        }
        $this->assertFalse($pdo->inTransaction());
        $this->assertSame(0, $pdo->query('SELECT COUNT(*) FROM entries')->fetchColumn());
        $this->assertSame('reused', $manager->transactional(static fn(): string => 'reused'));
    }

    public function testCaughtNestedRejectionLeavesOuterTransactionUsable(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $manager = new PdoTransactionManager($pdo);
        $manager->transactional(function () use ($manager, $pdo): void {
            try {
                $manager->transactional(static fn() => throw new RuntimeException('Must not run'));
                $this->fail('Expected nested transaction rejection.');
            } catch (NestedTransactionException) {
                $this->assertTrue($pdo->inTransaction());
            }
        });
        $this->assertFalse($pdo->inTransaction());
    }

    public function testUncaughtNestedRejectionRollsBackOuterWrites(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE entries (id INTEGER)');
        $manager = new PdoTransactionManager($pdo);
        try {
            $manager->transactional(static function () use ($manager, $pdo): void {
                $pdo->exec('INSERT INTO entries VALUES (1)');
                $manager->transactional(static fn() => throw new RuntimeException('Must not run'));
            });
            $this->fail('Expected nested transaction rejection.');
        } catch (NestedTransactionException) {
            $this->assertFalse($pdo->inTransaction());
            $this->assertSame(0, $pdo->query('SELECT COUNT(*) FROM entries')->fetchColumn());
        }
    }

    public function testRejectsAnExistingConnectionTransactionWithoutChangingIt(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->beginTransaction();
        try {
            new PdoTransactionManager($pdo)->transactional(static fn() => throw new RuntimeException('Must not run'));
            $this->fail('Expected existing transaction rejection.');
        } catch (NestedTransactionException) {
            $this->assertTrue($pdo->inTransaction());
        } finally {
            $pdo->rollBack();
        }
    }
}
