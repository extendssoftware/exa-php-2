<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Definition\Exception;

use ExtendsSoftware\ExaPHP\Cli\CliException;
use InvalidArgumentException;

/**
 * Indicates an invalid CLI argument definition.
 */
final class InvalidArgumentDefinitionException extends InvalidArgumentException implements CliException
{
}
