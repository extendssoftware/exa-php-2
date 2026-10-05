<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception;

use ExtendsSoftware\ExaPHP\Cli\ErrorHandling\UsageException;
use InvalidArgumentException;

/**
 * Indicates that a required positional argument was omitted.
 */
final class MissingArgumentException extends InvalidArgumentException implements UsageException
{
}
