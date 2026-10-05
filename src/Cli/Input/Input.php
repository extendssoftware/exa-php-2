<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Input;

use ExtendsSoftware\ExaPHP\Cli\Input\Exception\InvalidInputException;

use function is_bool;
use function is_string;

/**
 * Holds immutable parsed CLI input without converting or validating application values.
 */
final readonly class Input
{
    /**
     * Creates input with named arguments and options.
     *
     * Arguments and valued options remain strings, including empty strings. Flags use booleans. Absent entries are
     * omitted; a present false flag is distinct from an absent option. Names are case-sensitive and preserved as given.
     *
     * @param non-empty-string $command The selected command name.
     * @param array<non-empty-string, string> $arguments Named positional argument values.
     * @param array<non-empty-string, string|bool> $options Named option values and flags.
     *
     * @throws InvalidInputException When the command or an entry name is empty, or an entry has an invalid type.
     */
    public function __construct(public string $command, public array $arguments = [], public array $options = [])
    {
        if ($command === '') {
            throw new InvalidInputException('The CLI command name must not be empty.');
        }
        foreach ($arguments as $name => $value) {
            if (!is_string($name) || $name === '' || !is_string($value)) {
                throw new InvalidInputException('CLI arguments must map non-empty names to string values.');
            }
        }
        foreach ($options as $name => $value) {
            if (!is_string($name) || $name === '' || (!is_string($value) && !is_bool($value))) {
                throw new InvalidInputException('CLI options must map non-empty names to string or boolean values.');
            }
        }
    }
}
