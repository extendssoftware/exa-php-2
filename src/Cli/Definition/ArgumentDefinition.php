<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Definition;

use ExtendsSoftware\ExaPHP\Cli\Definition\Exception\InvalidArgumentDefinitionException;

use function preg_match;

/**
 * Defines one named positional argument.
 */
final readonly class ArgumentDefinition
{
    /**
     * Creates an argument definition without assigning or converting a value.
     *
     * @param string $name Case-sensitive ASCII name starting with a letter, followed by letters, digits, or hyphens.
     * @param string $description Human-readable help description.
     * @param ArgumentMode $mode Whether the argument must be supplied.
     *
     * @throws InvalidArgumentDefinitionException When the name is invalid.
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public ArgumentMode $mode = ArgumentMode::Required,
    ) {
        if (preg_match('/\A[A-Za-z][A-Za-z0-9-]*\z/', $name) !== 1) {
            throw new InvalidArgumentDefinitionException(
                'Argument names require a letter followed by letters, digits, or hyphens.',
            );
        }
    }
}
