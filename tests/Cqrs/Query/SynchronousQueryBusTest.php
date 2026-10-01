<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cqrs\Query;

use ExtendsSoftware\ExaPHP\Cqrs\Query\Query;
use ExtendsSoftware\ExaPHP\Cqrs\Query\QueryHandler;
use ExtendsSoftware\ExaPHP\Cqrs\Query\SynchronousQueryBus;
use ExtendsSoftware\ExaPHP\Cqrs\CqrsException;
use ExtendsSoftware\ExaPHP\Cqrs\Exception\QueryHandlerNotFoundException;
use ExtendsSoftware\ExaPHP\Cqrs\Exception\DuplicateQueryHandlerException;
use ExtendsSoftware\ExaPHP\Cqrs\Exception\InvalidQueryRegistrationException;
use ExtendsSoftware\ExaPHP\Tests\Cqrs\Query\Fixture\ChildQuery;
use ExtendsSoftware\ExaPHP\Tests\Cqrs\Query\Fixture\ParentQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use RuntimeException;
use stdClass;
use Throwable;
use TypeError;

use function class_alias;
use function get_debug_type;
use function preg_quote;
use function strtolower;

final class SynchronousQueryBusTest extends TestCase
{
    public function testDispatchesImmediatelyAndReusesRegisteredHandlers(): void
    {
        $query = new ParentQuery();
        $child = new ChildQuery();
        $handled = [];
        $handler = $this->createMock(QueryHandler::class);
        $handler->expects(self::exactly(2))->method('handle')->with(self::identicalTo($query))
            ->willReturnCallback(static function (Query $received) use (&$handled): mixed {
                $handled[] = $received;
                return $received;
            });
        $childHandler = $this->createMock(QueryHandler::class);
        $childHandler->expects(self::once())->method('handle')->with(self::identicalTo($child));

        $bus = new SynchronousQueryBus([
            ParentQuery::class => $handler,
            ChildQuery::class => $childHandler,
        ]);

        self::assertSame([], $handled);
        $bus->ask($query);
        self::assertSame([$query], $handled);
        $bus->ask($child);
        $bus->ask($query);
        self::assertSame([$query, $query], $handled);
    }

    #[DataProvider('handlerResults')]
    public function testReturnsTheHandlerResultUnchanged(mixed $result): void
    {
        $query = new ParentQuery();
        $handler = $this->createMock(QueryHandler::class);
        $handler->expects(self::once())->method('handle')->with(self::identicalTo($query))->willReturn($result);
        $bus = new SynchronousQueryBus([ParentQuery::class => $handler]);

        self::assertSame($result, $bus->ask($query));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function handlerResults(): iterable
    {
        yield 'null' => [null];
        yield 'false' => [false];
        yield 'zero' => [0];
        yield 'float' => [1.5];
        yield 'empty string' => [''];
        yield 'string' => ['Article title'];
        yield 'array' => [['title' => 'Article title']];
        yield 'object' => [new stdClass()];
    }

    public function testMissingRegistrationReportsTheQueryClass(): void
    {
        try {
            new SynchronousQueryBus([])->ask(new ParentQuery());
            self::fail('Expected a missing handler failure.');
        } catch (QueryHandlerNotFoundException $exception) {
            self::assertInstanceOf(CqrsException::class, $exception);
            self::assertStringContainsString(ParentQuery::class, $exception->getMessage());
        }
    }

    public function testDoesNotFallBackToAParentQueryHandler(): void
    {
        $handler = $this->createMock(QueryHandler::class);
        $handler->expects(self::never())->method('handle');
        $bus = new SynchronousQueryBus([ParentQuery::class => $handler]);

        $this->expectException(QueryHandlerNotFoundException::class);
        $bus->ask(new ChildQuery());
    }

    #[DataProvider('invalidQueryClasses')]
    public function testRejectsInvalidQueryClasses(int|string $class): void
    {
        $this->expectException(InvalidQueryRegistrationException::class);
        new SynchronousQueryBus([$class => $this->createStub(QueryHandler::class)]);
    }

    /**
     * @return iterable<string, array{int|string}>
     */
    public static function invalidQueryClasses(): iterable
    {
        yield 'integer key' => [0];
        yield 'empty key' => [''];
        yield 'unrelated class' => [stdClass::class];
        yield 'interface' => [Query::class];
        yield 'abstract class' => [TestCase::class];
        yield 'missing class' => ['ExaPHP\\MissingQuery'];
    }

    public function testPreservesTheCauseOfClassInspectionFailures(): void
    {
        try {
            new SynchronousQueryBus(['ExaPHP\\MissingQuery' => $this->createStub(QueryHandler::class)]);
            self::fail('Expected an invalid registration failure.');
        } catch (InvalidQueryRegistrationException $exception) {
            self::assertInstanceOf(CqrsException::class, $exception);
            self::assertInstanceOf(ReflectionException::class, $exception->getPrevious());
        }
    }

    #[DataProvider('invalidHandlers')]
    public function testRejectsInvalidHandlers(mixed $handler): void
    {
        $this->expectException(InvalidQueryRegistrationException::class);
        $this->expectExceptionMessageMatches(
            '/\AHandler for query "' . preg_quote(ParentQuery::class, '/')
            . '" must implement QueryHandler, ' . preg_quote(get_debug_type($handler), '/') . ' given\.\z/',
        );
        new SynchronousQueryBus([ParentQuery::class => $handler]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidHandlers(): iterable
    {
        yield 'null' => [null];
        yield 'boolean' => [false];
        yield 'object' => [new stdClass()];
        yield 'class name' => [QueryHandler::class];
        yield 'callable' => [static function (): void {
        }];
    }

    public function testNormalizesClassNameCasing(): void
    {
        $handler = $this->createMock(QueryHandler::class);
        $handler->expects(self::once())->method('handle');
        new SynchronousQueryBus([strtolower(ParentQuery::class) => $handler])->ask(new ParentQuery());
    }

    public function testNormalizesClassAliases(): void
    {
        $alias = __NAMESPACE__ . '\\AliasedQuery';
        class_alias(ParentQuery::class, $alias);
        $handler = $this->createMock(QueryHandler::class);
        $handler->expects(self::once())->method('handle');
        new SynchronousQueryBus([$alias => $handler])->ask(new ParentQuery());
    }

    public function testRejectsDuplicateCanonicalRegistrations(): void
    {
        $handler = $this->createStub(QueryHandler::class);
        $this->expectException(DuplicateQueryHandlerException::class);
        new SynchronousQueryBus([
            ParentQuery::class => $handler,
            strtolower(ParentQuery::class) => $handler,
        ]);
    }

    #[DataProvider('handlerFailures')]
    public function testPropagatesHandlerFailuresUnchangedAndCanDispatchAgain(Throwable $failure): void
    {
        $handler = $this->createMock(QueryHandler::class);
        $attempts = 0;
        $handler->expects(self::exactly(2))->method('handle')
            ->willReturnCallback(static function () use (&$attempts, $failure): null {
                if (++$attempts === 1) {
                    throw $failure;
                }

                return null;
            });
        $bus = new SynchronousQueryBus([ParentQuery::class => $handler]);

        try {
            $bus->ask(new ParentQuery());
            self::fail('Expected the handler failure.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }

        $bus->ask(new ParentQuery());
        self::assertSame(2, $attempts);
    }

    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function handlerFailures(): iterable
    {
        yield 'domain exception' => [new RuntimeException('Domain failure')];
        yield 'engine error' => [new TypeError('Handler error')];
        yield 'component exception' => [new QueryHandlerNotFoundException('Nested dispatch failed')];
    }

    public function testChangingTheInputMapDoesNotChangeRegistrations(): void
    {
        $handler = $this->createMock(QueryHandler::class);
        $handler->expects(self::once())->method('handle');
        $registrations = [ParentQuery::class => &$handler];
        $bus = new SynchronousQueryBus($registrations);
        $handler = $this->createStub(QueryHandler::class);
        $registrations = [];

        $bus->ask(new ParentQuery());
    }
}
