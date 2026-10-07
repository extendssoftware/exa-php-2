<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception;

use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use RuntimeException;

/**
 * Indicates that the current time could not be obtained to schedule a retry.
 */
final class RetryTimestampException extends RuntimeException implements MessagingException
{
}
