<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Clock;

use DateTimeImmutable;
use DateTimeZone;
use Override;

/**
 * Reads the system time in UTC.
 */
final readonly class SystemClock implements Clock
{
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
