<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Messaging\Exception;

use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use InvalidArgumentException;

/**
 * Indicates invalid messaging worker configuration.
 */
final class InvalidMessagingConfigurationException extends InvalidArgumentException implements IntegrationException
{
}
