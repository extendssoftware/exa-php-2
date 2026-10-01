<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Cqrs\Exception;

use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use InvalidArgumentException;

/**
 * Indicates invalid CQRS integration configuration.
 */
final class InvalidCqrsConfigurationException extends InvalidArgumentException implements IntegrationException
{
}
