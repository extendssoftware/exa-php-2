<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Pdo\Exception;

use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use InvalidArgumentException;

/**
 * Indicates invalid PDO integration configuration.
 */
final class InvalidPdoConfigurationException extends InvalidArgumentException implements IntegrationException
{
}
