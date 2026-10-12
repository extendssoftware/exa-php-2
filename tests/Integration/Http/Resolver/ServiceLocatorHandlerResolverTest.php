<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Http\Resolver;

use ExtendsSoftware\ExaPHP\Http\Handler\Exception\HandlerResolutionException;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Integration\Http\Resolver\ServiceLocatorHandlerResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ServiceLocatorHandlerResolverTest extends TestCase
{
    public function testResolvesRequestedHandler(): void
    {
        $handler = self::createStub(RequestHandler::class);
        $services = $this->createMock(ServiceLocator::class);
        $services->expects(self::once())->method('get')->with('article')->willReturn($handler);
        self::assertSame($handler, new ServiceLocatorHandlerResolver($services)->resolve('article'));
    }

    public function testRejectsServicesThatAreNotHandlers(): void
    {
        $services = self::createStub(ServiceLocator::class);
        $services->method('get')->willReturn(new stdClass());
        $this->expectException(HandlerResolutionException::class);
        $this->expectExceptionMessage('HTTP handler "article" must implement RequestHandler.');
        new ServiceLocatorHandlerResolver($services)->resolve('article');
    }

    public function testPreservesServiceResolutionFailureAsPreviousException(): void
    {
        $failure = new ServiceNotFoundException('Unknown service');
        $services = self::createStub(ServiceLocator::class);
        $services->method('get')->willThrowException($failure);
        try {
            new ServiceLocatorHandlerResolver($services)->resolve('article');
            self::fail('Expected handler resolution failure.');
        } catch (HandlerResolutionException $exception) {
            self::assertSame('Failed to resolve HTTP handler "article".', $exception->getMessage());
            self::assertSame($failure, $exception->getPrevious());
        }
    }
}
