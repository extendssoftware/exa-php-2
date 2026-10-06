<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Http\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\Exception\InvalidExceptionProblemDetailsMapperException;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetailsResponseFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Exception\InvalidHttpConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\ExceptionMappingFactory;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ExceptionMappingFactoryTest extends TestCase
{
    /** @param array<string, mixed> $values */
    #[DataProvider('invalidConfiguration')]
    public function testRejectsMalformedRegistrationConfiguration(array $values): void
    {
        $services = $this->createStub(ServiceLocator::class);
        $services->method('get')->willReturn(new Configuration($values));
        $this->expectException(InvalidHttpConfigurationException::class);
        new ExceptionMappingFactory()->create($services);
    }

    /** @return iterable<array{array<string, mixed>}> */
    public static function invalidConfiguration(): iterable
    {
        yield [['http' => null]];
        yield [['http' => ['exceptionMappers' => null]]];
        yield [['http' => ['exceptionMappers' => ['mapper']]]];
        yield [['http' => ['exceptionMappers' => ['' => 'mapper']]]];
        yield [['http' => ['exceptionMappers' => ['name' => '']]]];
        yield [['http' => ['exceptionMappers' => ['name' => 1]]]];
    }

    public function testRejectsIncompatibleMapperServices(): void
    {
        $services = $this->createStub(ServiceLocator::class);
        $services->method('get')->willReturnMap([
            [Configuration::class, new Configuration(['http' => ['exceptionMappers' => ['name' => 'mapper']]])],
            [ProblemDetailsResponseFactory::class, new ProblemDetailsResponseFactory()],
            ['mapper', new stdClass()],
        ]);
        $this->expectException(InvalidExceptionProblemDetailsMapperException::class);
        new ExceptionMappingFactory()->create($services);
    }
}
