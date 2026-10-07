<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Outbox\Exception;

use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use InvalidArgumentException;

/**
 * Indicates invalid outbox worker configuration.
 */
final class InvalidOutboxConfigurationException extends InvalidArgumentException implements IntegrationException
{
}
