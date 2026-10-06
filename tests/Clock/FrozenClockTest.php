<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Clock;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Clock\FrozenClock;
use PHPUnit\Framework\TestCase;

final class FrozenClockTest extends TestCase
{
    public function testRepeatedReadsPreserveTheSuppliedTimeAndTimezone(): void
    {
        $time = new DateTimeImmutable('2026-10-06T12:34:56.123456+02:00');
        $clock = new FrozenClock($time);

        self::assertSame($time, $clock->now());
        self::assertSame($time, $clock->now());
    }

    public function testModifyingAReturnedTimeDoesNotAdvanceTheClock(): void
    {
        $time = new DateTimeImmutable('2026-10-06T12:34:56.123456+02:00');
        $clock = new FrozenClock($time);
        $later = $clock->now()->modify('+1 hour');

        self::assertNotEquals($later, $clock->now());
        self::assertSame($time, $clock->now());
    }
}
