<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Http\Exception;

use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use InvalidArgumentException;

/**
 * Indicates malformed HTTP integration configuration or incompatible services.
 */
final class InvalidHttpConfigurationException extends InvalidArgumentException implements IntegrationException
{
}
