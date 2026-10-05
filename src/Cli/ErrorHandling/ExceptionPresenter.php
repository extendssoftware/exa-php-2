<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\ErrorHandling;

use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use Throwable;

/**
 * Presents a CLI failure through error output and selects its exit code.
 */
interface ExceptionPresenter
{
    /**
     * Writes an error presentation to stderr and returns a failure exit code without terminating the process.
     *
     * @param Throwable $exception The failure to present.
     * @param Output $output The destination for error output.
     *
     * @return int The nonzero exit code representing the failure.
     *
     * @throws Throwable When presentation fails, including output failures.
     */
    public function present(Throwable $exception, Output $output): int;
}
