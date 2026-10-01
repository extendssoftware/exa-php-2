<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Command;

use ExtendsSoftware\ExaPHP\Cqrs\CqrsException;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;
use Throwable;

/**
 * Dispatches commands for handling without returning a result.
 */
interface CommandBus
{
    /**
     * Dispatches a command to its handler.
     *
     * Dispatch failures use the CQRS exception contract. Unhandled execution failures propagate unchanged.
     *
     * @param Command $command The command to dispatch.
     * @param DispatchContext $context The application-defined execution metadata.
     *
     * @return void
     *
     * @throws CqrsException When the command cannot be dispatched.
     * @throws Throwable When execution throws an unhandled exception or error, propagated unchanged.
     */
    public function dispatch(Command $command, DispatchContext $context = new DispatchContext()): void;
}
