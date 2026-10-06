<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Clock;

use DateTimeImmutable;

/**
 * Provides the current time.
 */
interface Clock
{
    /**
     * Returns the current time according to this clock.
     *
     * @return DateTimeImmutable The current time.
     *
     * @throws ClockException When the current time cannot be obtained.
     */
    public function now(): DateTimeImmutable;
}
