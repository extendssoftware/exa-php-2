<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Cli\Resolver;

use ExtendsSoftware\ExaPHP\Cli\Handler\Exception\HandlerResolutionException;
use ExtendsSoftware\ExaPHP\Cli\Handler\CommandHandler;
use ExtendsSoftware\ExaPHP\Integration\Cli\Resolver\ServiceLocatorHandlerResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ServiceLocatorHandlerResolverTest extends TestCase
{
    public function testResolvesRequestedHandler(): void
    {
        $handler = $this->createStub(CommandHandler::class);
        $services = $this->createMock(ServiceLocator::class);
        $services->expects($this->once())->method('get')->with('article')->willReturn($handler);
        $this->assertSame($handler, new ServiceLocatorHandlerResolver($services)->resolve('article'));
    }

    public function testRejectsServicesThatAreNotHandlers(): void
    {
        $services = $this->createStub(ServiceLocator::class);
        $services->method('get')->willReturn(new stdClass());
        $this->expectException(HandlerResolutionException::class);
        $this->expectExceptionMessage('CLI handler "article" must implement CommandHandler.');
        new ServiceLocatorHandlerResolver($services)->resolve('article');
    }

    public function testPreservesServiceResolutionFailureAsPreviousException(): void
    {
        $failure = new ServiceNotFoundException('Unknown service');
        $services = $this->createStub(ServiceLocator::class);
        $services->method('get')->willThrowException($failure);
        try {
            new ServiceLocatorHandlerResolver($services)->resolve('article');
            $this->fail('Expected handler resolution failure.');
        } catch (HandlerResolutionException $exception) {
            $this->assertSame('Failed to resolve CLI handler "article".', $exception->getMessage());
            $this->assertSame($failure, $exception->getPrevious());
        }
    }
}
