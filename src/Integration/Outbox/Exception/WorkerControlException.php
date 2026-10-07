<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Outbox\Exception;

use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use RuntimeException;

/**
 * Indicates failure to manage the outbox worker lifecycle.
 */
final class WorkerControlException extends RuntimeException implements IntegrationException
{
}
