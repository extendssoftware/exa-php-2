<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Cli\Exception;

use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use InvalidArgumentException;

/**
 * Indicates malformed CLI integration configuration or incompatible services.
 */
final class InvalidCliConfigurationException extends InvalidArgumentException implements IntegrationException
{
}
