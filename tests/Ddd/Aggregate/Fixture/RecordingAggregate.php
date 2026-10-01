<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Ddd\Aggregate\Fixture;

use ExtendsSoftware\ExaPHP\Ddd\Aggregate\AbstractAggregateRoot;
use ExtendsSoftware\ExaPHP\Ddd\Event\DomainEvent;

/**
 * Exercises aggregate event recording through public behavior.
 */
final class RecordingAggregate extends AbstractAggregateRoot
{
    /**
     * Creates an aggregate without parent initialization.
     *
     * @param DomainEvent $event The event recorded by the aggregate's behavior.
     */
    public function __construct(private readonly DomainEvent $event)
    {
    }

    /**
     * Performs behavior that records the supplied event.
     *
     * @return void
     */
    public function act(): void
    {
        $this->recordEvent($this->event);
    }
}
