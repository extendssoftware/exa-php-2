<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Event;

use ExtendsSoftware\ExaPHP\Event\Event;
use ExtendsSoftware\ExaPHP\Event\EventException;
use ExtendsSoftware\ExaPHP\Event\Exception\DuplicateEventRegistrationException;
use ExtendsSoftware\ExaPHP\Event\Exception\InvalidEventRegistrationException;
use ExtendsSoftware\ExaPHP\Event\Listener\EventListener;
use ExtendsSoftware\ExaPHP\Event\SynchronousEventDispatcher;
use ExtendsSoftware\ExaPHP\Tests\Event\Fixture\ChildEvent;
use ExtendsSoftware\ExaPHP\Tests\Event\Fixture\ParentEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use RuntimeException;
use stdClass;
use Throwable;
use TypeError;

use function class_alias;
use function strtolower;

final class SynchronousEventDispatcherTest extends TestCase
{
    public function testInvokesListenersInOrderWithTheOriginalEventAndReusesInstances(): void
    {
        $event = new ParentEvent();
        $calls = [];
        $first = $this->createMock(EventListener::class);
        $first->expects(self::exactly(4))->method('handle')->with(self::identicalTo($event))
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'first';
            });
        $second = $this->createMock(EventListener::class);
        $second->expects(self::exactly(2))->method('handle')->with(self::identicalTo($event))
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'second';
            });
        $dispatcher = new SynchronousEventDispatcher([ParentEvent::class => [$first, $second, $first]]);
        self::assertSame([], $calls);
        $dispatcher->dispatch($event);
        self::assertSame(['first', 'second', 'first'], $calls);
        $dispatcher->dispatch($event);
        self::assertSame(['first', 'second', 'first', 'first', 'second', 'first'], $calls);
    }

    public function testRoutesDifferentEventClassesToTheirOwnListeners(): void
    {
        $parent = new ParentEvent();
        $child = new ChildEvent();
        $parentListener = $this->createMock(EventListener::class);
        $parentListener->expects(self::once())->method('handle')->with(self::identicalTo($parent));
        $childListener = $this->createMock(EventListener::class);
        $childListener->expects(self::once())->method('handle')->with(self::identicalTo($child));
        $dispatcher = new SynchronousEventDispatcher([
            ParentEvent::class => [$parentListener],
            ChildEvent::class => [$childListener],
        ]);
        $dispatcher->dispatch($child);
        $dispatcher->dispatch($parent);
    }

    public function testIgnoresUnregisteredEventsAndEmptyListsWithoutParentFallback(): void
    {
        $listener = $this->createMock(EventListener::class);
        $listener->expects(self::never())->method('handle');
        new SynchronousEventDispatcher([ParentEvent::class => [$listener]])->dispatch(new ChildEvent());
        new SynchronousEventDispatcher([])->dispatch(new ParentEvent());
        new SynchronousEventDispatcher([ParentEvent::class => []])->dispatch(new ParentEvent());
    }

    #[DataProvider('invalidRegistrations')]
    public function testRejectsInvalidRegistrations(array $registrations): void
    {
        $this->expectException(InvalidEventRegistrationException::class);
        new SynchronousEventDispatcher($registrations);
    }

    /**
     * @return iterable<array{array<array-key, mixed>}>
     */
    public static function invalidRegistrations(): iterable
    {
        yield [[0 => []]];
        yield [['' => []]];
        yield [[stdClass::class => []]];
        yield [[Event::class => []]];
        yield [[TestCase::class => []]];
        yield [['MissingEventClass' => []]];
        yield [[ParentEvent::class => null]];
        yield [[ParentEvent::class => new stdClass()]];
        yield [[ParentEvent::class => ['named' => new stdClass()]]];
        yield [[ParentEvent::class => [1 => new stdClass()]]];
        yield [[ParentEvent::class => [null]]];
        yield [[ParentEvent::class => [new stdClass()]]];
        yield [[ParentEvent::class => [EventListener::class]]];
    }

    public function testPreservesClassInspectionFailures(): void
    {
        try {
            new SynchronousEventDispatcher(['MissingEventClass' => []]);
            self::fail('Expected invalid registration.');
        } catch (InvalidEventRegistrationException $exception) {
            self::assertInstanceOf(EventException::class, $exception);
            self::assertInstanceOf(ReflectionException::class, $exception->getPrevious());
        }
    }

    public function testNormalizesAliasesAndClassNameCasing(): void
    {
        $alias = __NAMESPACE__ . '\\AliasedEvent';
        class_alias(ParentEvent::class, $alias);
        $listener = $this->createMock(EventListener::class);
        $listener->expects(self::exactly(2))->method('handle');
        new SynchronousEventDispatcher([$alias => [$listener]])->dispatch(new ParentEvent());
        new SynchronousEventDispatcher([strtolower(ParentEvent::class) => [$listener]])->dispatch(new ParentEvent());
    }

    public function testRejectsDuplicateCanonicalRegistrationsEvenForEmptyLists(): void
    {
        $this->expectException(DuplicateEventRegistrationException::class);
        new SynchronousEventDispatcher([ParentEvent::class => [], strtolower(ParentEvent::class) => []]);
    }

    #[DataProvider('failures')]
    public function testStopsOnFailureAndPropagatesItUnchanged(Throwable $failure): void
    {
        $first = $this->createMock(EventListener::class);
        $first->expects(self::exactly(2))->method('handle')->willThrowException($failure);
        $second = $this->createMock(EventListener::class);
        $second->expects(self::never())->method('handle');
        $dispatcher = new SynchronousEventDispatcher([ParentEvent::class => [$first, $second]]);
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                $dispatcher->dispatch(new ParentEvent());
                self::fail('Expected listener failure.');
            } catch (Throwable $exception) {
                self::assertSame($failure, $exception);
            }
        }
    }

    /**
     * @return iterable<array{Throwable}>
     */
    public static function failures(): iterable
    {
        yield [new RuntimeException('Domain failure')];
        yield [new TypeError('Engine failure')];
        yield [new InvalidEventRegistrationException('Nested failure')];
    }

    public function testCopiesRegistrationArraysWithoutRetainingReferences(): void
    {
        $listener = $this->createMock(EventListener::class);
        $listener->expects(self::once())->method('handle');
        $list = [&$listener];
        $registrations = [ParentEvent::class => &$list];
        $dispatcher = new SynchronousEventDispatcher($registrations);
        $listener = $this->createStub(EventListener::class);
        $list = [];
        $registrations = [];
        $dispatcher->dispatch(new ParentEvent());
    }
}
