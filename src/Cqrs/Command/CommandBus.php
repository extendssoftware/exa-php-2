<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Command;

use ExtendsSoftware\ExaPHP\Cqrs\CqrsException;
use Throwable;

/**
 * Dispatches commands for handling without returning a result.
 */
interface CommandBus
{
    /**
     * Dispatches a command to its handler.
     *
     * Dispatch failures use the CQRS exception contract. Handler exceptions and errors propagate unchanged.
     *
     * @param Command $command The command to dispatch.
     *
     * @return void
     *
     * @throws CqrsException When the command cannot be dispatched.
     * @throws Throwable When the handler throws an exception or error, propagated unchanged.
     */
    public function dispatch(Command $command): void;
}
