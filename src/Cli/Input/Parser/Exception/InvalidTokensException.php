<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception;

use ExtendsSoftware\ExaPHP\Cli\ErrorHandling\UsageException;
use InvalidArgumentException;

/**
 * Indicates that parser input is not a list of string tokens.
 */
final class InvalidTokensException extends InvalidArgumentException implements UsageException
{
}
