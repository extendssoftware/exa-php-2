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
     * Returns the current system time in UTC independently of PHP's default timezone.
     *
     * @return DateTimeImmutable The current system time in UTC.
     */
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
