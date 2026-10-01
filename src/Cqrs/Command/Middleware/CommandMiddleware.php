<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Command\Middleware;

use ExtendsSoftware\ExaPHP\Cqrs\Command\Command;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;
use Throwable;

/**
 * Wraps command execution with application behavior.
 */
interface CommandMiddleware
{
    /**
     * Processes a command with explicit execution metadata.
     *
     * @param Command $command The command to execute.
     * @param DispatchContext $context The execution metadata.
     * @param CommandExecution $next The remaining pipeline; omit execution to short-circuit.
     *
     * @return void
     *
     * @throws Throwable When execution fails.
     */
    public function process(Command $command, DispatchContext $context, CommandExecution $next): void;
}
