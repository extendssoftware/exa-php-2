<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Routing\Exception;

use ExtendsSoftware\ExaPHP\Cli\CliException;
use LogicException;

/**
 * Indicates a repeated command name in a registry.
 */
final class DuplicateCommandException extends LogicException implements CliException
{
}
