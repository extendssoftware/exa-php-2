<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Definition;

use ExtendsSoftware\ExaPHP\Cli\Definition\Exception\InvalidOptionDefinitionException;

use function preg_match;

/**
 * Defines a named CLI flag or an option requiring a value when present.
 */
final readonly class OptionDefinition
{
    /**
     * Creates an option definition; the option itself may be omitted regardless of its mode.
     *
     * @param string $name Case-sensitive ASCII name starting with a letter, followed by letters, digits, or hyphens.
     * @param string $description Human-readable help description.
     * @param OptionMode $mode Whether a supplied option takes a value.
     * @param string|null $shortAlias An optional single ASCII letter, without a leading hyphen.
     *
     * @throws InvalidOptionDefinitionException When the name or alias is invalid.
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public OptionMode $mode = OptionMode::Flag,
        public ?string $shortAlias = null,
    ) {
        if (preg_match('/\A[A-Za-z][A-Za-z0-9-]*\z/', $name) !== 1) {
            throw new InvalidOptionDefinitionException(
                'Option names require a letter followed by letters, digits, or hyphens.',
            );
        }
        if ($shortAlias !== null && preg_match('/\A[A-Za-z]\z/', $shortAlias) !== 1) {
            throw new InvalidOptionDefinitionException('Short option aliases must be a single ASCII letter.');
        }
    }
}
