<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Outbox\Retry\Exception;

use ExtendsSoftware\ExaPHP\Outbox\OutboxException;
use InvalidArgumentException;

/**
 * Indicates invalid retry policy configuration, input, or delay.
 */
final class InvalidRetryPolicyException extends InvalidArgumentException implements OutboxException
{
}
