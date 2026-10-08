<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Http\Resolver;

use ExtendsSoftware\ExaPHP\Http\Middleware\Exception\MiddlewareResolutionException;
use ExtendsSoftware\ExaPHP\Http\Middleware\Middleware;
use ExtendsSoftware\ExaPHP\Integration\Http\Resolver\ServiceLocatorMiddlewareResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ServiceLocatorMiddlewareResolverTest extends TestCase
{
    public function testResolvesRequestedMiddleware(): void
    {
        $middleware = $this->createStub(Middleware::class);
        $services = $this->createMock(ServiceLocator::class);
        $services->expects($this->once())->method('get')->with('article')->willReturn($middleware);
        $this->assertSame($middleware, new ServiceLocatorMiddlewareResolver($services)->resolve('article'));
    }

    public function testRejectsServicesThatAreNotMiddlewares(): void
    {
        $services = $this->createStub(ServiceLocator::class);
        $services->method('get')->willReturn(new stdClass());
        $this->expectException(MiddlewareResolutionException::class);
        $this->expectExceptionMessageIs('HTTP middleware "article" must implement Middleware.');
        new ServiceLocatorMiddlewareResolver($services)->resolve('article');
    }

    public function testPreservesServiceResolutionFailureAsPreviousException(): void
    {
        $failure = new ServiceNotFoundException('Unknown service');
        $services = $this->createStub(ServiceLocator::class);
        $services->method('get')->willThrowException($failure);
        try {
            new ServiceLocatorMiddlewareResolver($services)->resolve('article');
            $this->fail('Expected middleware resolution failure.');
        } catch (MiddlewareResolutionException $exception) {
            $this->assertSame('Failed to resolve HTTP middleware "article".', $exception->getMessage());
            $this->assertSame($failure, $exception->getPrevious());
        }
    }
}
