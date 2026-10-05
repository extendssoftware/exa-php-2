<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Routing\Exception;

use ExtendsSoftware\ExaPHP\Cli\CliException;
use InvalidArgumentException;

/**
 * Indicates malformed command registry registrations.
 */
final class InvalidCommandRegistrationException extends InvalidArgumentException implements CliException
{
}
