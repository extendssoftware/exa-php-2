<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Clock;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Clock\SystemClock;
use PHPUnit\Framework\TestCase;

use function date_default_timezone_get;
use function date_default_timezone_set;

final class SystemClockTest extends TestCase
{
    public function testReturnsCurrentSystemTimeInUtcRegardlessOfTheDefaultTimezone(): void
    {
        $timezone = date_default_timezone_get();
        date_default_timezone_set('Pacific/Auckland');

        try {
            $before = new DateTimeImmutable();
            $now = new SystemClock()->now();
            $after = new DateTimeImmutable();

            self::assertGreaterThanOrEqual($before, $now);
            self::assertLessThanOrEqual($after, $now);
            self::assertSame('UTC', $now->getTimezone()->getName());
        } finally {
            date_default_timezone_set($timezone);
        }
    }
}
