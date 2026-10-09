<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Domain\Aggregate;

use Override;
use ExtendsSoftware\ExaPHP\Domain\Event\DomainEvent;
use ExtendsSoftware\ExaPHP\Domain\Event\RecordedEvents;

/**
 * Provides event recording for aggregate roots through inheritance.
 *
 * Subclasses record events from domain behavior. No parent constructor call is required.
 */
abstract class AbstractAggregateRoot implements AggregateRoot
{
    /**
     * Lazily initialized collection of pending domain events.
     */
    private ?RecordedEvents $events = null;

    /**
     * Gives the cloned aggregate an independent pending-event collection.
     *
     * Event objects retain their identity. Subclasses overriding this method must call parent::__clone().
     *
     * @return void
     */
    public function __clone(): void
    {
        if ($this->events !== null) {
            $this->events = clone $this->events;
        }
    }

    /**
     * Records an event without publishing it.
     *
     * @param DomainEvent $event The event that occurred.
     *
     * @return void
     */
    final protected function recordEvent(DomainEvent $event): void
    {
        ($this->events ??= new RecordedEvents())->record($event);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    final public function releaseEvents(): array
    {
        return $this->events?->release() ?? [];
    }
}
