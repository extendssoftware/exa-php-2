<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Clock;

use DateTimeImmutable;
use Override;

/**
 * Provides a fixed time without advancing it.
 */
final readonly class FrozenClock implements Clock
{
    /**
     * Creates a clock fixed at the supplied time.
     *
     * @param DateTimeImmutable $time The fixed time, preserving its timezone and precision.
     */
    public function __construct(private DateTimeImmutable $time)
    {
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}
