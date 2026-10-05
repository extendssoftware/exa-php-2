<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Input\Parser;

use ExtendsSoftware\ExaPHP\Cli\CliException;
use ExtendsSoftware\ExaPHP\Cli\Definition\CommandDefinition;
use ExtendsSoftware\ExaPHP\Cli\Input\Input;

/**
 * Converts command argument tokens into named input using a command definition.
 */
interface InputParser
{
    /**
     * Parses tokens for an already selected command without resolving or executing its handler.
     *
     * @param CommandDefinition $definition The selected command and its input declarations.
     * @param list<string> $tokens Argument tokens excluding the executable and command name.
     *
     * @return Input Named arguments and options with string values preserved.
     *
     * @throws CliException When the tokens cannot satisfy the command's input structure.
     */
    public function parse(CommandDefinition $definition, array $tokens): Input;
}
