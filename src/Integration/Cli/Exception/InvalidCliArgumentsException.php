<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Cli\Exception;

use ExtendsSoftware\ExaPHP\Cli\ErrorHandling\UsageException;
use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use InvalidArgumentException;

/**
 * Indicates missing command selection or malformed process arguments.
 */
final class InvalidCliArgumentsException extends InvalidArgumentException implements IntegrationException, UsageException
{
}
