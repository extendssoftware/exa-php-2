<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Http\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Integration\Http\Exception\InvalidHttpConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\ExceptionHandlingMiddlewareFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\MiddlewarePipelineFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\RouterFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\RouteCollectionFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\UrlGeneratorFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\RoutingRequestHandlerFactory;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class HttpFactoriesTest extends TestCase
{
    #[DataProvider('invalidConfigurations')]
    public function testRejectsMalformedConfiguration(string $section, mixed $value): void
    {
        $configuration = new Configuration($section === 'http' ? ['http' => $value] : ['http' => [$section => $value]]);
        $services = $this->createMock(ServiceLocator::class);
        $services->expects($this->once())->method('get')->with(Configuration::class)->willReturn($configuration);
        $this->expectException(InvalidHttpConfigurationException::class);
        $factory = $section === 'middleware' ? new MiddlewarePipelineFactory() : new RouteCollectionFactory();
        $factory->create($services);
    }

    /** @return iterable<array{string, mixed}> */
    public static function invalidConfigurations(): iterable
    {
        yield ['http', null];
        yield ['routes', null];
        yield ['routes', ['route' => new stdClass()]];
        yield ['routes', ['' => 'handler']];
        yield ['middleware', null];
        yield ['middleware', ['service']];
        yield ['middleware', ['name' => '']];
        yield ['middleware', ['name' => new stdClass()]];
    }

    #[DataProvider('factories')]
    public function testRejectsIncompatibleServices(object $factory): void
    {
        $services = $this->createStub(ServiceLocator::class);
        $services->method('get')->willReturn(new stdClass());
        $this->expectException(InvalidHttpConfigurationException::class);
        $factory->create($services);
    }

    /** @return iterable<array{object}> */
    public static function factories(): iterable
    {
        yield [new RouterFactory()];
        yield [new RouteCollectionFactory()];
        yield [new UrlGeneratorFactory()];
        yield [new MiddlewarePipelineFactory()];
        yield [new RoutingRequestHandlerFactory()];
        yield [new ExceptionHandlingMiddlewareFactory()];
    }
}
