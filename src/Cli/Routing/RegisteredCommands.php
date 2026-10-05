<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Routing;

use ExtendsSoftware\ExaPHP\Cli\Definition\CommandDefinition;
use ExtendsSoftware\ExaPHP\Cli\Routing\Exception\CommandNotFoundException;
use ExtendsSoftware\ExaPHP\Cli\Routing\Exception\DuplicateCommandException;
use ExtendsSoftware\ExaPHP\Cli\Routing\Exception\InvalidCommandRegistrationException;
use Override;

use function array_is_list;
use function array_values;

/**
 * Stores immutable command registrations in declaration order.
 */
final readonly class RegisteredCommands implements CommandRegistry
{
    /**
     * Definitions indexed by case-sensitive command name.
     *
     * @var array<string, CommandDefinition>
     */
    private array $commands;

    /**
     * Registers definitions without resolving handler identifiers.
     *
     * @param list<CommandDefinition> $commands The command definitions in listing order.
     *
     * @throws InvalidCommandRegistrationException When registrations are not a list of definitions.
     * @throws DuplicateCommandException When command names repeat, including the same definition twice.
     */
    public function __construct(array $commands = [])
    {
        if (!array_is_list($commands)) {
            throw new InvalidCommandRegistrationException('Command registrations must be a list.');
        }
        $registered = [];
        foreach ($commands as $command) {
            if (!$command instanceof CommandDefinition) {
                throw new InvalidCommandRegistrationException(
                    'Command registrations must be CommandDefinition instances.',
                );
            }
            if (isset($registered[$command->name])) {
                throw new DuplicateCommandException('Command "' . $command->name . '" is already registered.');
            }
            $registered[$command->name] = $command;
        }
        $this->commands = $registered;
    }

    /**
     * Returns the definition registered under an exact name.
     *
     * @param string $name The case-sensitive command name.
     *
     * @return CommandDefinition The registered definition, retaining its identity.
     *
     * @throws CommandNotFoundException When the command name is absent.
     */
    #[Override]
    public function get(string $name): CommandDefinition
    {
        return $this->commands[$name]
            ?? throw new CommandNotFoundException('Command "' . $name . '" is not registered.');
    }

    /**
     * Lists definitions in their original registration order.
     *
     * @return list<CommandDefinition> The registered definitions.
     */
    #[Override]
    public function all(): array
    {
        return array_values($this->commands);
    }
}
