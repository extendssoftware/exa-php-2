<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Routing\Exception;

use ExtendsSoftware\ExaPHP\Cli\ErrorHandling\UsageException;
use RuntimeException;

/**
 * Indicates that a requested command name is not registered.
 */
final class CommandNotFoundException extends RuntimeException implements UsageException
{
}
