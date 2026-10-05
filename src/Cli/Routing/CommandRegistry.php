<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Routing;

use ExtendsSoftware\ExaPHP\Cli\Definition\CommandDefinition;
use ExtendsSoftware\ExaPHP\Cli\Routing\Exception\CommandNotFoundException;

/**
 * Provides command definitions for lookup and listing without executing handlers.
 */
interface CommandRegistry
{
    /**
     * Returns the definition registered under an exact, case-sensitive name.
     *
     * @param string $name The command name.
     *
     * @return CommandDefinition The registered definition.
     *
     * @throws CommandNotFoundException When the name is not registered.
     */
    public function get(string $name): CommandDefinition;

    /**
     * Lists all registered command definitions.
     *
     * @return list<CommandDefinition> Available definitions.
     */
    public function all(): array;
}
