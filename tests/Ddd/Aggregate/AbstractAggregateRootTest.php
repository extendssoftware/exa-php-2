<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Ddd\Aggregate;

use ExtendsSoftware\ExaPHP\Ddd\Event\DomainEvent;
use ExtendsSoftware\ExaPHP\Tests\Ddd\Aggregate\Fixture\RecordingAggregate;
use PHPUnit\Framework\TestCase;

final class AbstractAggregateRootTest extends TestCase
{
    public function testStartsEmptyWithAnIndependentSubclassConstructor(): void
    {
        $aggregate = new RecordingAggregate($this->createStub(DomainEvent::class));

        self::assertSame([], $aggregate->releaseEvents());
    }

    public function testReleasesRecordedOccurrencesAndCanRecordAgain(): void
    {
        $event = $this->createStub(DomainEvent::class);
        $aggregate = new RecordingAggregate($event);
        $aggregate->act();
        $aggregate->act();
        $released = $aggregate->releaseEvents();

        self::assertSame([$event, $event], $released);
        self::assertSame([], $aggregate->releaseEvents());

        $aggregate->act();
        self::assertSame([$event], $aggregate->releaseEvents());
        self::assertSame([$event, $event], $released);
    }

    public function testAggregatesHaveIndependentPendingEvents(): void
    {
        $event = $this->createStub(DomainEvent::class);
        $first = new RecordingAggregate($event);
        $second = new RecordingAggregate($event);
        $first->act();

        self::assertSame([], $second->releaseEvents());
        self::assertSame([$event], $first->releaseEvents());
    }

    public function testCloningBeforeRecordingKeepsCollectionsIndependent(): void
    {
        $event = $this->createStub(DomainEvent::class);
        $original = new RecordingAggregate($event);
        $copy = clone $original;
        $copy->act();

        self::assertSame([], $original->releaseEvents());
        self::assertSame([$event], $copy->releaseEvents());
    }

    public function testCloningCopiesPendingEventsWithoutSharingTheCollection(): void
    {
        $event = $this->createStub(DomainEvent::class);
        $original = new RecordingAggregate($event);
        $original->act();
        $copy = clone $original;
        $copy->act();

        self::assertSame([$event], $original->releaseEvents());
        self::assertSame([$event, $event], $copy->releaseEvents());
        $original->act();
        self::assertSame([], $copy->releaseEvents());
        self::assertSame([$event], $original->releaseEvents());
    }
}
