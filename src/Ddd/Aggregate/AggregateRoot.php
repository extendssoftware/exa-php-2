<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Ddd\Aggregate;

use ExtendsSoftware\ExaPHP\Ddd\Event\DomainEvent;

/**
 * Exposes domain events recorded by an aggregate root.
 */
interface AggregateRoot
{
    /**
     * Returns recorded events in occurrence order and clears the pending events.
     *
     * Each recorded occurrence is returned once. Without new events, subsequent calls return an empty list.
     * Releasing events does not publish them.
     *
     * @return list<DomainEvent> The recorded events, or an empty list when none are pending.
     */
    public function releaseEvents(): array;
}
