<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception;

use ExtendsSoftware\ExaPHP\Cli\ErrorHandling\UsageException;
use InvalidArgumentException;

/**
 * Indicates that the same option was supplied more than once.
 */
final class DuplicateOptionException extends InvalidArgumentException implements UsageException
{
}
