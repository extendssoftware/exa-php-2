<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Logging\Exception;

use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use InvalidArgumentException;

/**
 * Indicates invalid logging integration configuration.
 */
final class InvalidLoggingConfigurationException extends InvalidArgumentException implements IntegrationException
{
}
