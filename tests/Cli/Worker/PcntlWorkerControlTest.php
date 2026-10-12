<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cli\Worker;

use ExtendsSoftware\ExaPHP\Cli\Worker\Exception\WorkerControlException;
use ExtendsSoftware\ExaPHP\Cli\Worker\PcntlWorkerControl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use const PHP_INT_MIN;

final class PcntlWorkerControlTest extends TestCase
{
    #[DataProvider('nonPositiveDurations')]
    public function testRejectsNonPositiveWaitDuration(int $seconds): void
    {
        $control = new PcntlWorkerControl();

        $this->expectException(WorkerControlException::class);
        $this->expectExceptionMessage('Worker wait duration must be positive seconds.');

        $control->wait($seconds);
    }

    /** @return iterable<string, array{int}> */
    public static function nonPositiveDurations(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'minimum integer' => [PHP_INT_MIN];
    }
}
