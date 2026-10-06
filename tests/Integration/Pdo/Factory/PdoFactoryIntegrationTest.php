<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Pdo\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Integration\Pdo\Exception\PdoConnectionException;
use ExtendsSoftware\ExaPHP\Integration\Pdo\Factory\PdoFactory;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class PdoFactoryIntegrationTest extends TestCase
{
    public function testCreatesIndependentConnectionsWithExceptionMode(): void
    {
        $locator = $this->createStub(ServiceLocator::class);
        $locator->method('get')->willReturn(new Configuration(['pdo' => ['dsn' => 'sqlite::memory:']]));
        $factory = new PdoFactory();
        $pdo = $factory->create($locator);
        $this->assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        $this->assertNotSame($pdo, $factory->create($locator));
    }

    public function testHonorsExplicitDriverOptions(): void
    {
        $locator = $this->createStub(ServiceLocator::class);
        $locator->method('get')->willReturn(new Configuration(['pdo' => [
            'dsn' => 'sqlite::memory:',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ]]));
        $pdo = new PdoFactory()->create($locator);
        $this->assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        $this->assertSame(PDO::FETCH_ASSOC, $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    public function testTranslatesConnectionFailuresAndPreservesTheCause(): void
    {
        $locator = $this->createStub(ServiceLocator::class);
        $locator->method('get')->willReturn(new Configuration(['pdo' => ['dsn' => 'exa_missing_driver:test']]));
        try {
            new PdoFactory()->create($locator);
            $this->fail('Expected connection failure.');
        } catch (PdoConnectionException $exception) {
            $this->assertInstanceOf(PDOException::class, $exception->getPrevious());
            $this->assertSame('Unable to establish the configured PDO connection.', $exception->getMessage());
        }
    }
}
