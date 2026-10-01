<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Command;

use Throwable;

/**
 * Handles a command without returning a result.
 *
 * @template TCommand of Command
 */
interface CommandHandler
{
    /**
     * Performs the action requested by the command.
     *
     * @param TCommand $command The command to handle.
     *
     * @return void
     *
     * @throws Throwable When command handling raises a domain exception or another exception or error.
     */
    public function handle(Command $command): void;
}
