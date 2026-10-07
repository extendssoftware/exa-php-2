<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cli\Worker;

use ExtendsSoftware\ExaPHP\Cli\CliException;
use ExtendsSoftware\ExaPHP\Cli\Worker\Exception\WorkerControlException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WorkerControlExceptionTest extends TestCase
{
    public function testIdentifiesCliFailuresAndPreservesTheirCause(): void
    {
        $cause = new RuntimeException('Signal registration failed.');
        $exception = new WorkerControlException('Worker startup failed.', 0, $cause);

        self::assertInstanceOf(CliException::class, $exception);
        self::assertSame($cause, $exception->getPrevious());
    }
}
