<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cqrs\Command\Middleware;

use ExtendsSoftware\ExaPHP\Cqrs\Command\Command;
use ExtendsSoftware\ExaPHP\Cqrs\Command\CommandHandler;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Middleware\CommandExecution;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Middleware\CommandMiddleware;
use ExtendsSoftware\ExaPHP\Cqrs\Command\SynchronousCommandBus;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Exception\InvalidCommandMiddlewareException;
use ExtendsSoftware\ExaPHP\Tests\Cqrs\Command\Fixture\ParentCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Throwable;
use TypeError;

use function count;

final class CommandMiddlewareTest extends TestCase
{
    public function testWrapsHandlerInConfiguredOrderAndPassesOriginalContext(): void
    {
        $calls = [];
        $command = new ParentCommand();
        $context = new DispatchContext([new stdClass()]);
        $middleware = [];
        foreach (['first', 'second'] as $name) {
            $entry = $this->createMock(CommandMiddleware::class);
            $entry->expects(self::once())->method('process')
                ->with($command, self::identicalTo($context), self::anything())
                ->willReturnCallback(static function (
                    Command $command,
                    DispatchContext $context,
                    CommandExecution $next,
                ) use (&$calls, $name): void {
                    $calls[] = $name . ':before';
                    $next->execute($command, $context);
                    $calls[] = $name . ':after';
                });
            $middleware[] = $entry;
        }
        $handler = $this->createMock(CommandHandler::class);
        $handler->expects(self::once())->method('handle')->with(self::identicalTo($command))
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'handler';
            });
        $bus = new SynchronousCommandBus([ParentCommand::class => $handler], $middleware);
        self::assertSame([], $calls);
        $bus->dispatch($command, $context);
        self::assertSame(['first:before', 'second:before', 'handler', 'second:after', 'first:after'], $calls);
    }

    public function testCanShortCircuitBeforeHandlerLookup(): void
    {
        $middleware = $this->createMock(CommandMiddleware::class);
        $middleware->expects(self::once())->method('process');
        new SynchronousCommandBus([], [$middleware])->dispatch(new ParentCommand());
    }

    public function testCanForwardAReplacementCommandAndContext(): void
    {
        $command = new ParentCommand();
        $context = new DispatchContext();
        $replacementContext = $context->with(new stdClass());
        $first = $this->createMock(CommandMiddleware::class);
        $first->expects(self::once())->method('process')->willReturnCallback(static function (
            Command $incoming,
            DispatchContext $context,
            CommandExecution $next,
        ) use ($command, $replacementContext): void {
            $next->execute($command, $replacementContext);
        });
        $second = $this->createMock(CommandMiddleware::class);
        $second->expects(self::once())->method('process')
            ->with(self::identicalTo($command), self::identicalTo($replacementContext), self::anything())
            ->willReturnCallback(static function (
                Command $command,
                DispatchContext $context,
                CommandExecution $next,
            ): void {
                $next->execute($command, $context);
            });
        $handler = $this->createMock(CommandHandler::class);
        $handler->expects(self::once())->method('handle')->with(self::identicalTo($command));
        new SynchronousCommandBus([ParentCommand::class => $handler], [$first, $second])
            ->dispatch(new ParentCommand(), $context);
        self::assertFalse($context->has(stdClass::class));
    }

    public function testNestedAndRepeatedDispatchStartWithIndependentEmptyContexts(): void
    {
        $contexts = [];
        $bus = null;
        $middleware = $this->createMock(CommandMiddleware::class);
        $middleware->expects(self::exactly(3))->method('process')->willReturnCallback(static function (
            Command $command,
            DispatchContext $context,
            CommandExecution $next,
        ) use (&$contexts, &$bus): void {
            $contexts[] = $context;
            if (count($contexts) === 1) {
                $bus->dispatch($command);
            }
            $next->execute($command, $context);
        });
        $handler = $this->createMock(CommandHandler::class);
        $handler->expects(self::exactly(3))->method('handle');
        $bus = new SynchronousCommandBus([ParentCommand::class => $handler], [$middleware]);
        $bus->dispatch(new ParentCommand());
        $bus->dispatch(new ParentCommand());
        self::assertNotSame($contexts[0], $contexts[1]);
        self::assertNotSame($contexts[1], $contexts[2]);
    }

    public function testMiddlewareCanCatchTheOriginalHandlerFailure(): void
    {
        $failure = new RuntimeException('Handler failed');
        $caught = null;
        $handler = $this->createMock(CommandHandler::class);
        $handler->expects(self::once())->method('handle')->willThrowException($failure);
        $middleware = $this->createMock(CommandMiddleware::class);
        $middleware->expects(self::once())->method('process')->willReturnCallback(static function (
            Command $command,
            DispatchContext $context,
            CommandExecution $next,
        ) use (&$caught): void {
            try {
                $next->execute($command, $context);
            } catch (RuntimeException $exception) {
                $caught = $exception;
            }
        });
        new SynchronousCommandBus([ParentCommand::class => $handler], [$middleware])->dispatch(new ParentCommand());
        self::assertSame($failure, $caught);
    }

    #[DataProvider('failures')]
    public function testMiddlewareFailuresPropagateUnchanged(Throwable $failure): void
    {
        $middleware = $this->createMock(CommandMiddleware::class);
        $middleware->expects(self::once())->method('process')->willThrowException($failure);
        try {
            new SynchronousCommandBus([], [$middleware])->dispatch(new ParentCommand());
            self::fail('Expected middleware failure.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /**
     * @return iterable<array{Throwable}>
     */
    public static function failures(): iterable
    {
        yield [new RuntimeException('Denied')];
        yield [new TypeError('Invalid middleware behavior')];
    }

    #[DataProvider('invalidMiddleware')]
    public function testRejectsInvalidMiddleware(array $middleware): void
    {
        $this->expectException(InvalidCommandMiddlewareException::class);
        new SynchronousCommandBus([], $middleware);
    }

    /**
     * @return iterable<array{array<array-key, mixed>}>
     */
    public static function invalidMiddleware(): iterable
    {
        yield [[null]];
        yield [[new stdClass()]];
        yield [['named' => new stdClass()]];
    }
}
