<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception;

use ExtendsSoftware\ExaPHP\Cli\ErrorHandling\UsageException;
use InvalidArgumentException;

/**
 * Indicates that a supplied option is missing its required value.
 */
final class MissingOptionValueException extends InvalidArgumentException implements UsageException
{
}
