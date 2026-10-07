<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Outbox\Processing\Exception;

use ExtendsSoftware\ExaPHP\Outbox\OutboxException;
use RuntimeException;

/**
 * Indicates that the current time could not be obtained to schedule a retry.
 */
final class RetryTimestampException extends RuntimeException implements OutboxException
{
}
