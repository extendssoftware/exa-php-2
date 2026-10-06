<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Pdo\Transaction;

use ExtendsSoftware\ExaPHP\Integration\Pdo\Transaction\PdoTransactionManager;
use ExtendsSoftware\ExaPHP\Transaction\Exception\TransactionCommitException;
use ExtendsSoftware\ExaPHP\Transaction\Exception\TransactionRollbackException;
use ExtendsSoftware\ExaPHP\Transaction\Exception\TransactionStartException;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PdoTransactionManagerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, class-string, bool}>
     */
    public static function lifecycleFailures(): iterable
    {
        yield 'state inspection exception' => ['inTransaction', TransactionStartException::class, true];
        yield 'error mode inspection exception' => ['getAttribute', TransactionStartException::class, true];
        yield 'start exception' => ['beginTransaction', TransactionStartException::class, true];
        yield 'start false' => ['beginTransaction', TransactionStartException::class, false];
        yield 'commit exception' => ['commit', TransactionCommitException::class, true];
        yield 'commit false' => ['commit', TransactionCommitException::class, false];
    }

    #[DataProvider('lifecycleFailures')]
    public function testTranslatesLifecycleFailures(string $method, string $expected, bool $throws): void
    {
        $pdo = $this->createMock(PDO::class);
        $failure = new PDOException('Driver failure');
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('getAttribute')->willReturn(PDO::ERRMODE_EXCEPTION);
        if ($method === 'commit') {
            $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        }
        $call = $pdo->expects($this->once())->method($method);
        if ($throws) {
            $call->willThrowException($failure);
        } else {
            $call->willReturn(false);
        }
        $pdo->expects($this->never())->method('rollBack');
        $invocations = 0;
        try {
            new PdoTransactionManager($pdo)->transactional(static function () use (&$invocations): void {
                ++$invocations;
            });
            $this->fail('Expected lifecycle failure.');
        } catch (TransactionStartException | TransactionCommitException $exception) {
            $this->assertInstanceOf($expected, $exception);
            $this->assertSame($throws ? $failure : null, $exception->getPrevious());
            $this->assertSame($method === 'commit' ? 1 : 0, $invocations);
        }
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function rollbackFailures(): iterable
    {
        yield 'exception' => [true];
        yield 'false return' => [false];
    }

    #[DataProvider('rollbackFailures')]
    public function testPreservesOperationAndRollbackFailures(bool $throws): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('getAttribute')->willReturn(PDO::ERRMODE_EXCEPTION);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->never())->method('commit');
        $failure = new RuntimeException('Operation failed');
        $rollbackFailure = new PDOException('Rollback failed');
        $rollback = $pdo->expects($this->once())->method('rollBack');
        if ($throws) {
            $rollback->willThrowException($rollbackFailure);
        } else {
            $rollback->willReturn(false);
        }
        try {
            new PdoTransactionManager($pdo)->transactional(static fn() => throw $failure);
            $this->fail('Expected rollback failure.');
        } catch (TransactionRollbackException $exception) {
            $this->assertSame($failure, $exception->getPrevious());
            if ($throws) {
                $this->assertSame($rollbackFailure, $exception->rollbackFailure);
            } else {
                $this->assertInstanceOf(PDOException::class, $exception->rollbackFailure);
            }
        }
    }
}
