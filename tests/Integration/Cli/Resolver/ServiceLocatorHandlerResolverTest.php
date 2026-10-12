<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Cli\Resolver;

use ExtendsSoftware\ExaPHP\Cli\Handler\CommandHandler;
use ExtendsSoftware\ExaPHP\Cli\Handler\Exception\HandlerResolutionException;
use ExtendsSoftware\ExaPHP\Integration\Cli\Resolver\ServiceLocatorHandlerResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ServiceLocatorHandlerResolverTest extends TestCase
{
    public function testResolvesRequestedHandler(): void
    {
        $handler = self::createStub(CommandHandler::class);
        $services = $this->createMock(ServiceLocator::class);
        $services->expects(self::once())->method('get')->with('article')->willReturn($handler);
        self::assertSame($handler, new ServiceLocatorHandlerResolver($services)->resolve('article'));
    }

    public function testRejectsServicesThatAreNotHandlers(): void
    {
        $services = self::createStub(ServiceLocator::class);
        $services->method('get')->willReturn(new stdClass());
        $this->expectException(HandlerResolutionException::class);
        $this->expectExceptionMessage('CLI handler "article" must implement CommandHandler.');
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
            self::assertSame('Failed to resolve CLI handler "article".', $exception->getMessage());
            self::assertSame($failure, $exception->getPrevious());
        }
    }
}
