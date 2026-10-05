<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Definition\Exception;

use ExtendsSoftware\ExaPHP\Cli\CliException;
use InvalidArgumentException;

/**
 * Indicates an invalid CLI command definition.
 */
final class InvalidCommandDefinitionException extends InvalidArgumentException implements CliException
{
}
