<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Handler;

use ExtendsSoftware\ExaPHP\Cli\Handler\Exception\HandlerResolutionException;

/**
 * Resolves CLI command handlers by identifier.
 */
interface HandlerResolver
{
    /**
     * Returns the handler associated with an identifier.
     *
     * @param non-empty-string $id The handler identifier.
     *
     * @return CommandHandler The resolved handler.
     *
     * @throws HandlerResolutionException When the identifier cannot be resolved to a command handler.
     */
    public function resolve(string $id): CommandHandler;
}
