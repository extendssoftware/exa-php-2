<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Outbox\Worker;

use ExtendsSoftware\ExaPHP\Integration\Outbox\Worker\PcntlWorkerControl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function function_exists;
use function getmypid;
use function microtime;
use function pcntl_alarm;
use function pcntl_async_signals;
use function pcntl_signal;
use function pcntl_signal_get_handler;
use function posix_kill;

use const SIGALRM;
use const SIGTERM;

final class PcntlWorkerControlIntegrationTest extends TestCase
{
    #[RunInSeparateProcess]
    #[DataProvider('signals')]
    public function testSignalsRequestStopAndPreviousStateIsRestored(int $signal): void
    {
        if (!function_exists('pcntl_async_signals') || !function_exists('posix_kill')) {
            self::markTestSkipped('Requires PCNTL and POSIX.');
        }
        $previous = static function (): void {};
        pcntl_signal($signal, $previous);
        pcntl_async_signals(false);
        $control = new PcntlWorkerControl();
        $control->start();
        try {
            self::assertFalse($control->stopRequested());
            posix_kill(getmypid(), $signal);
            self::assertTrue($control->stopRequested());
        } finally {
            $control->finish();
        }
        self::assertSame($previous, pcntl_signal_get_handler($signal));
        self::assertFalse(pcntl_async_signals());
        $control->start();
        try {
            self::assertFalse($control->stopRequested());
        } finally {
            $control->finish();
        }
    }

    /** @return iterable<array{int}> */
    public static function signals(): iterable
    {
        // Numeric POSIX signal values also allow discovery on PHP builds without PCNTL.
        yield [15];
        yield [2];
    }

    #[RunInSeparateProcess]
    public function testSignalInterruptsIdleWaiting(): void
    {
        if (!function_exists('pcntl_async_signals') || !function_exists('posix_kill')) {
            self::markTestSkipped('Requires PCNTL and POSIX.');
        }
        pcntl_signal(SIGALRM, static function (): void {
            posix_kill(getmypid(), SIGTERM);
        });
        $control = new PcntlWorkerControl();
        $control->start();
        try {
            pcntl_alarm(1);
            $started = microtime(true);
            $control->wait(10);
            self::assertTrue($control->stopRequested());
            self::assertLessThan(5, microtime(true) - $started);
        } finally {
            pcntl_alarm(0);
            $control->finish();
        }
    }
}
