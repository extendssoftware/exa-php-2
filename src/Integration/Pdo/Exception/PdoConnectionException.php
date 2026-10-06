<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Pdo\Exception;

use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use RuntimeException;

/**
 * Indicates failure to create a PDO connection.
 */
final class PdoConnectionException extends RuntimeException implements IntegrationException
{
}
