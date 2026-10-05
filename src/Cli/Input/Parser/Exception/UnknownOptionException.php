<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception;

use ExtendsSoftware\ExaPHP\Cli\ErrorHandling\UsageException;
use InvalidArgumentException;

/**
 * Indicates an option or short alias absent from the command definition.
 */
final class UnknownOptionException extends InvalidArgumentException implements UsageException
{
}
