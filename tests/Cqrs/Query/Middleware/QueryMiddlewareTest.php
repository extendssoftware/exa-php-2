<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cqrs\Query\Middleware;

use ExtendsSoftware\ExaPHP\Cqrs\Query\Query;
use ExtendsSoftware\ExaPHP\Cqrs\Query\QueryHandler;
use ExtendsSoftware\ExaPHP\Cqrs\Query\Middleware\QueryExecution;
use ExtendsSoftware\ExaPHP\Cqrs\Query\Middleware\QueryMiddleware;
use ExtendsSoftware\ExaPHP\Cqrs\Query\SynchronousQueryBus;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;
use ExtendsSoftware\ExaPHP\Cqrs\Query\Exception\InvalidQueryMiddlewareException;
use ExtendsSoftware\ExaPHP\Tests\Cqrs\Query\Fixture\ParentQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Throwable;
use TypeError;

use function count;

final class QueryMiddlewareTest extends TestCase
{
    public function testWrapsHandlerInConfiguredOrderAndPassesOriginalContext(): void
    {
        $calls = [];
        $query = new ParentQuery();
        $context = new DispatchContext([new stdClass()]);
        $middleware = [];
        foreach (['first', 'second'] as $name) {
            $entry = $this->createMock(QueryMiddleware::class);
            $entry->expects(self::once())->method('process')
                ->with($query, self::identicalTo($context), self::anything())
                ->willReturnCallback(static function (
                    Query $query,
                    DispatchContext $context,
                    QueryExecution $next,
                ) use (&$calls, $name): mixed {
                    $calls[] = $name . ':before';
                    $result = $next->execute($query, $context);
                    $calls[] = $name . ':after';
                    return $result;
                });
            $middleware[] = $entry;
        }
        $handler = $this->createMock(QueryHandler::class);
        $handler->expects(self::once())->method('handle')->with(self::identicalTo($query))
            ->willReturnCallback(static function () use (&$calls): mixed {
                $calls[] = 'handler';
                return 'result';
            });
        $bus = new SynchronousQueryBus([ParentQuery::class => $handler], $middleware);
        self::assertSame([], $calls);
        self::assertSame('result', $bus->ask($query, $context));
        self::assertSame(['first:before', 'second:before', 'handler', 'second:after', 'first:after'], $calls);
    }

    public function testCanShortCircuitBeforeHandlerLookup(): void
    {
        $middleware = $this->createMock(QueryMiddleware::class);
        $middleware->expects(self::once())->method('process')->willReturn('cached');
        self::assertSame('cached', new SynchronousQueryBus([], [$middleware])->ask(new ParentQuery()));
    }

    public function testCanForwardAReplacementQueryAndContext(): void
    {
        $query = new ParentQuery();
        $context = new DispatchContext();
        $replacementContext = $context->with(new stdClass());
        $first = $this->createMock(QueryMiddleware::class);
        $first->expects(self::once())->method('process')->willReturnCallback(static function (
            Query $incoming,
            DispatchContext $context,
            QueryExecution $next,
        ) use ($query, $replacementContext): mixed {
            return $next->execute($query, $replacementContext);
        });
        $second = $this->createMock(QueryMiddleware::class);
        $second->expects(self::once())->method('process')
            ->with(self::identicalTo($query), self::identicalTo($replacementContext), self::anything())
            ->willReturnCallback(static function (
                Query $query,
                DispatchContext $context,
                QueryExecution $next,
            ): mixed {
                return $next->execute($query, $context);
            });
        $handler = $this->createMock(QueryHandler::class);
        $handler->expects(self::once())->method('handle')->with(self::identicalTo($query));
        new SynchronousQueryBus([ParentQuery::class => $handler], [$first, $second])
            ->ask(new ParentQuery(), $context);
        self::assertFalse($context->has(stdClass::class));
    }

    public function testNestedAndRepeatedDispatchStartWithIndependentEmptyContexts(): void
    {
        $contexts = [];
        $bus = null;
        $middleware = $this->createMock(QueryMiddleware::class);
        $middleware->expects(self::exactly(3))->method('process')->willReturnCallback(static function (
            Query $query,
            DispatchContext $context,
            QueryExecution $next,
        ) use (&$contexts, &$bus): mixed {
            $contexts[] = $context;
            if (count($contexts) === 1) {
                $bus->ask($query);
            }
            return $next->execute($query, $context);
        });
        $handler = $this->createMock(QueryHandler::class);
        $handler->expects(self::exactly(3))->method('handle');
        $bus = new SynchronousQueryBus([ParentQuery::class => $handler], [$middleware]);
        $bus->ask(new ParentQuery());
        $bus->ask(new ParentQuery());
        self::assertNotSame($contexts[0], $contexts[1]);
        self::assertNotSame($contexts[1], $contexts[2]);
    }

    public function testMiddlewareCanCatchTheOriginalHandlerFailure(): void
    {
        $failure = new RuntimeException('Handler failed');
        $caught = null;
        $handler = $this->createMock(QueryHandler::class);
        $handler->expects(self::once())->method('handle')->willThrowException($failure);
        $middleware = $this->createMock(QueryMiddleware::class);
        $middleware->expects(self::once())->method('process')->willReturnCallback(static function (
            Query $query,
            DispatchContext $context,
            QueryExecution $next,
        ) use (&$caught): mixed {
            try {
                return $next->execute($query, $context);
            } catch (RuntimeException $exception) {
                $caught = $exception;
                return 'recovered';
            }
        });
        new SynchronousQueryBus([ParentQuery::class => $handler], [$middleware])->ask(new ParentQuery());
        self::assertSame($failure, $caught);
    }

    public function testCanTransformTheDownstreamResult(): void
    {
        $handler = $this->createStub(QueryHandler::class);
        $handler->method('handle')->willReturn(10);
        $middleware = $this->createMock(QueryMiddleware::class);
        $middleware->expects(self::once())->method('process')->willReturnCallback(static function (
            Query $query,
            DispatchContext $context,
            QueryExecution $next,
        ): mixed {
            return $next->execute($query, $context) + 5;
        });
        $bus = new SynchronousQueryBus([ParentQuery::class => $handler], [$middleware]);
        self::assertSame(15, $bus->ask(new ParentQuery()));
    }

    #[DataProvider('results')]
    public function testPreservesResultValuesThroughMiddleware(mixed $result): void
    {
        $handler = $this->createStub(QueryHandler::class);
        $handler->method('handle')->willReturn($result);
        $middleware = $this->createMock(QueryMiddleware::class);
        $middleware->expects(self::once())->method('process')->willReturnCallback(static function (
            Query $query,
            DispatchContext $context,
            QueryExecution $next,
        ): mixed {
            return $next->execute($query, $context);
        });
        $bus = new SynchronousQueryBus([ParentQuery::class => $handler], [$middleware]);
        self::assertSame($result, $bus->ask(new ParentQuery()));
    }

    /**
     * @return iterable<array{mixed}>
     */
    public static function results(): iterable
    {
        yield [null];
        yield [false];
        yield [0];
        yield [''];
        yield [['title' => 'Article']];
        yield [new stdClass()];
    }

    #[DataProvider('failures')]
    public function testMiddlewareFailuresPropagateUnchanged(Throwable $failure): void
    {
        $middleware = $this->createMock(QueryMiddleware::class);
        $middleware->expects(self::once())->method('process')->willThrowException($failure);
        try {
            new SynchronousQueryBus([], [$middleware])->ask(new ParentQuery());
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
        $this->expectException(InvalidQueryMiddlewareException::class);
        new SynchronousQueryBus([], $middleware);
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
