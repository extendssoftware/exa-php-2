<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Event\Exception;

use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use InvalidArgumentException;

/**
 * Indicates invalid event integration configuration.
 */
final class InvalidEventConfigurationException extends InvalidArgumentException implements IntegrationException
{
}
