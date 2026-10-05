<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli;

/**
 * Provides conventional exit codes without restricting application-specific integer codes.
 */
enum ExitCode: int
{
    /**
     * Successful execution.
     */
    case Success = 0;

    /**
     * Failed execution.
     */
    case Failure = 1;

    /**
     * Invalid command usage.
     */
    case InvalidUsage = 2;
}
