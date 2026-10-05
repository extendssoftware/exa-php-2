<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Definition;

use ExtendsSoftware\ExaPHP\Cli\Definition\Exception\InvalidCommandDefinitionException;

use function array_is_list;
use function preg_match;

/**
 * Describes a CLI command and its input structure without resolving its handler.
 */
final readonly class CommandDefinition
{
    /**
     * Creates a command with ordered arguments and options.
     *
     * Names are case-sensitive. Argument and option names occupy separate namespaces. Short aliases are distinct
     * from long option names. All required arguments must precede optional arguments.
     *
     * @param string $name ASCII command segments separated by colons, each starting with a letter.
     * @param non-empty-string $handlerId The lazily resolved handler service identifier.
     * @param string $description Human-readable help description.
     * @param list<ArgumentDefinition> $arguments Positional arguments in declaration order.
     * @param list<OptionDefinition> $options Options in declaration order.
     *
     * @throws InvalidCommandDefinitionException When names, registration lists, or argument ordering are invalid.
     */
    public function __construct(
        public string $name,
        public string $handlerId,
        public string $description = '',
        public array $arguments = [],
        public array $options = [],
    ) {
        if (preg_match('/\A[A-Za-z][A-Za-z0-9-]*(?::[A-Za-z][A-Za-z0-9-]*)*\z/', $name) !== 1) {
            throw new InvalidCommandDefinitionException('Command names require letter-led segments separated by colons.');
        }
        if ($handlerId === '') {
            throw new InvalidCommandDefinitionException('Command handler identifiers must not be empty.');
        }
        if (!array_is_list($arguments) || !array_is_list($options)) {
            throw new InvalidCommandDefinitionException('Command arguments and options must be lists.');
        }
        $names = [];
        $optional = false;
        foreach ($arguments as $argument) {
            if (!$argument instanceof ArgumentDefinition) {
                throw new InvalidCommandDefinitionException('Command arguments must be ArgumentDefinition instances.');
            }
            if (isset($names[$argument->name])) {
                throw new InvalidCommandDefinitionException('Argument names must be unique within a command.');
            }
            if ($optional && $argument->mode === ArgumentMode::Required) {
                throw new InvalidCommandDefinitionException('Required arguments must precede optional arguments.');
            }
            $names[$argument->name] = true;
            $optional = $argument->mode === ArgumentMode::Optional;
        }
        $names = [];
        $aliases = [];
        foreach ($options as $option) {
            if (!$option instanceof OptionDefinition) {
                throw new InvalidCommandDefinitionException('Command options must be OptionDefinition instances.');
            }
            if (isset($names[$option->name])) {
                throw new InvalidCommandDefinitionException('Option names must be unique within a command.');
            }
            if ($option->shortAlias !== null && isset($aliases[$option->shortAlias])) {
                throw new InvalidCommandDefinitionException('Short option aliases must be unique within a command.');
            }
            $names[$option->name] = true;
            if ($option->shortAlias !== null) {
                $aliases[$option->shortAlias] = true;
            }
        }
    }
}
