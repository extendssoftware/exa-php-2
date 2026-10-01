<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Command\Middleware;

use ExtendsSoftware\ExaPHP\Cqrs\Command\Command;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;
use Throwable;

/**
 * Executes the remaining command pipeline.
 */
interface CommandExecution
{
    /**
     * Processes a command with explicit execution metadata.
     *
     * @param Command $command The command to execute.
     * @param DispatchContext $context The execution metadata.
     *
     * @return void
     *
     * @throws Throwable When execution fails.
     */
    public function execute(Command $command, DispatchContext $context): void;
}
