<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Ddd\Event;

use ExtendsSoftware\ExaPHP\Event\Event;

/**
 * Describes a fact that occurred in the domain.
 */
interface DomainEvent extends Event
{
}
