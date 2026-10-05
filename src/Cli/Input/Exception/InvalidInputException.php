<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Input\Exception;

use ExtendsSoftware\ExaPHP\Cli\CliException;
use InvalidArgumentException;

/**
 * Indicates malformed parsed CLI input.
 */
final class InvalidInputException extends InvalidArgumentException implements CliException
{
}
