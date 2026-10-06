<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Pdo\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Integration\Pdo\Exception\InvalidPdoConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\Pdo\Factory\PdoFactory;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class PdoFactoryTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurations(): iterable
    {
        foreach ([PDO::ERRMODE_SILENT, PDO::ERRMODE_WARNING, null, '2'] as $mode) {
            yield [[
                'pdo' => ['dsn' => 'sqlite::memory:', 'options' => [PDO::ATTR_ERRMODE => $mode]],
            ]];
        }
        yield 'missing section' => [[]];
        yield 'invalid section' => [['pdo' => false]];
        yield 'missing dsn' => [['pdo' => []]];
        yield 'empty dsn' => [['pdo' => ['dsn' => ' ']]];
        yield 'invalid dsn' => [['pdo' => ['dsn' => 12]]];
        yield 'invalid username' => [['pdo' => ['dsn' => 'sqlite::memory:', 'username' => false]]];
        yield 'invalid password' => [['pdo' => ['dsn' => 'sqlite::memory:', 'password' => []]]];
        yield 'invalid options' => [['pdo' => ['dsn' => 'sqlite::memory:', 'options' => false]]];
        yield 'null options' => [['pdo' => ['dsn' => 'sqlite::memory:', 'options' => null]]];
        yield 'invalid option key' => [['pdo' => ['dsn' => 'sqlite::memory:', 'options' => ['bad' => 1]]]];
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('invalidConfigurations')]
    public function testRejectsInvalidConfiguration(array $values): void
    {
        $locator = $this->createStub(ServiceLocator::class);
        $locator->method('get')->willReturn(new Configuration($values));
        $this->expectException(InvalidPdoConfigurationException::class);
        new PdoFactory()->create($locator);
    }

    public function testRejectsIncorrectConfigurationService(): void
    {
        $locator = $this->createStub(ServiceLocator::class);
        $locator->method('get')->willReturn(new stdClass());
        $this->expectException(InvalidPdoConfigurationException::class);
        new PdoFactory()->create($locator);
    }

    public function testPreservesConfigurationResolutionFailure(): void
    {
        $locator = $this->createStub(ServiceLocator::class);
        $failure = new ServiceNotFoundException('Missing configuration');
        $locator->method('get')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        new PdoFactory()->create($locator);
    }
}
