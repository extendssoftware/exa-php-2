<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Input\Parser;

use ExtendsSoftware\ExaPHP\Cli\Definition\ArgumentMode;
use ExtendsSoftware\ExaPHP\Cli\Definition\CommandDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\OptionMode;
use ExtendsSoftware\ExaPHP\Cli\Input\Input;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\DuplicateOptionException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\InvalidTokensException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\MissingArgumentException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\MissingOptionValueException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\UnexpectedArgumentException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\UnexpectedOptionValueException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\UnknownOptionException;
use Override;

use function array_is_list;
use function array_key_exists;
use function count;
use function explode;
use function is_string;
use function str_starts_with;
use function substr;

/**
 * Parses positional arguments, long options, and single-letter aliases from shell-tokenized input.
 *
 * Options may surround positional arguments. Double hyphen ends option parsing; a lone hyphen is positional.
 * Short bundles and attached short values are unsupported. A separate option value may not begin with a hyphen
 * unless it is exactly a lone hyphen; use --name=value for other hyphen-prefixed values. No quoting is reinterpreted.
 */
final readonly class ArgvInputParser implements InputParser
{
    /**
     * {@inheritDoc}
     *
     * Flags are true when present; omitted entries remain absent. Empty explicit values are preserved.
     * Repeated aliases and long names for the same option are rejected.
     *
     * @throws InvalidTokensException When tokens are not a list of strings.
     * @throws UnknownOptionException When an option name, alias, or unsupported short syntax is supplied.
     * @throws MissingOptionValueException When a valued option has no unambiguous value.
     * @throws UnexpectedOptionValueException When a flag is supplied using equals syntax.
     * @throws DuplicateOptionException When an option occurs multiple times.
     * @throws MissingArgumentException When a required argument is absent.
     * @throws UnexpectedArgumentException When more positional values are supplied than declared.
     */
    #[Override]
    public function parse(CommandDefinition $definition, array $tokens): Input
    {
        if (!array_is_list($tokens)) {
            throw new InvalidTokensException('CLI tokens must be a list of strings.');
        }
        foreach ($tokens as $token) {
            if (!is_string($token)) {
                throw new InvalidTokensException('CLI tokens must be a list of strings.');
            }
        }
        $long = [];
        $short = [];
        foreach ($definition->options as $option) {
            $long[$option->name] = $option;
            if ($option->shortAlias !== null) {
                $short[$option->shortAlias] = $option;
            }
        }
        $arguments = [];
        $options = [];
        $position = 0;
        $parseOptions = true;
        for ($index = 0, $length = count($tokens); $index < $length; ++$index) {
            $token = $tokens[$index];
            if ($parseOptions && $token === '--') {
                $parseOptions = false;
                continue;
            }
            if (!$parseOptions || $token === '-' || !str_starts_with($token, '-')) {
                $argument = $definition->arguments[$position++] ?? null;
                if ($argument === null) {
                    throw new UnexpectedArgumentException('Unexpected positional argument: ' . $token . '.');
                }
                $arguments[$argument->name] = $token;
                continue;
            }
            $value = null;
            if (str_starts_with($token, '--')) {
                $parts = explode('=', substr($token, 2), 2);
                $option = $long[$parts[0]] ?? null;
                $value = $parts[1] ?? null;
            } else {
                $option = $short[substr($token, 1)] ?? null;
            }
            if ($option === null) {
                throw new UnknownOptionException('Unknown option: ' . $token . '.');
            }
            if (array_key_exists($option->name, $options)) {
                throw new DuplicateOptionException('Option "' . $option->name . '" was supplied more than once.');
            }
            if ($option->mode === OptionMode::Flag) {
                if ($value !== null) {
                    throw new UnexpectedOptionValueException('Flag "' . $option->name . '" does not accept a value.');
                }
                $options[$option->name] = true;
                continue;
            }
            if ($value === null) {
                $value = $tokens[$index + 1] ?? null;
                if ($value === null || ($value !== '-' && str_starts_with($value, '-'))) {
                    throw new MissingOptionValueException('Option "' . $option->name . '" requires a value.');
                }
                ++$index;
            }
            $options[$option->name] = $value;
        }
        foreach ($definition->arguments as $argument) {
            if ($argument->mode === ArgumentMode::Required && !array_key_exists($argument->name, $arguments)) {
                throw new MissingArgumentException('Required argument "' . $argument->name . '" is missing.');
            }
        }

        return new Input($definition->name, $arguments, $options);
    }
}
