<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception;

use ExtendsSoftware\ExaPHP\Cli\ErrorHandling\UsageException;
use InvalidArgumentException;

/**
 * Indicates that a flag was supplied with a value.
 */
final class UnexpectedOptionValueException extends InvalidArgumentException implements UsageException
{
}
