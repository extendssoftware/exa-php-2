<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Handler;

use ExtendsSoftware\ExaPHP\Cli\Input\Input;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use Throwable;

/**
 * Executes a CLI command using parsed input and explicit output channels.
 */
interface CommandHandler
{
    /**
     * Handles input and returns an exit code without terminating the process.
     *
     * @param Input $input The parsed command arguments and options.
     * @param Output $output The output channels available to the command.
     *
     * @return int The exit code; zero indicates success and nonzero indicates failure.
     *
     * @throws Throwable When command execution or output fails.
     */
    public function handle(Input $input, Output $output): int;
}
