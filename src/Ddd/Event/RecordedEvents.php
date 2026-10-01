<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Ddd\Event;

/**
 * Collects pending domain events for an aggregate.
 *
 * Events retain their object identity. Recording the same instance twice records two occurrences.
 */
final class RecordedEvents
{
    /**
     * Pending events in recording order.
     *
     * @var list<DomainEvent>
     */
    private array $events = [];

    /**
     * Appends an event without publishing it.
     *
     * @param DomainEvent $event The event that occurred.
     *
     * @return void
     */
    public function record(DomainEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * Returns pending events in recording order and clears the collection.
     *
     * @return list<DomainEvent> The recorded events, retaining their identity and order.
     */
    public function release(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }
}
