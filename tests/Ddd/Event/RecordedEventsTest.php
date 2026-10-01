<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Ddd\Event;

use ExtendsSoftware\ExaPHP\Ddd\Event\DomainEvent;
use ExtendsSoftware\ExaPHP\Ddd\Event\RecordedEvents;
use PHPUnit\Framework\TestCase;

final class RecordedEventsTest extends TestCase
{
    public function testReleasesAnEmptyListWithoutRecordedEvents(): void
    {
        self::assertSame([], new RecordedEvents()->release());
    }

    public function testPreservesEventIdentityOrderAndRepeatedOccurrences(): void
    {
        $first = new class implements DomainEvent {
        };
        $second = new class implements DomainEvent {
        };
        $events = new RecordedEvents();
        $events->record($first);
        $events->record($second);
        $events->record($first);

        self::assertSame([$first, $second, $first], $events->release());
        self::assertSame([], $events->release());
    }

    public function testCanRecordNewEventsAfterReleaseWithoutChangingTheReleasedList(): void
    {
        $first = new class implements DomainEvent {
        };
        $second = new class implements DomainEvent {
        };
        $events = new RecordedEvents();
        $events->record($first);
        $released = $events->release();
        $events->record($second);

        self::assertSame([$first], $released);
        self::assertSame([$second], $events->release());
        self::assertSame([], $events->release());
    }

    public function testCollectionsHaveIndependentPendingEvents(): void
    {
        $event = new class implements DomainEvent {
        };
        $first = new RecordedEvents();
        $second = new RecordedEvents();
        $first->record($event);

        self::assertSame([], $second->release());
        self::assertSame([$event], $first->release());
    }
}
