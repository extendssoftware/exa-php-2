<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Outbox\Exception;

use ExtendsSoftware\ExaPHP\Outbox\OutboxException;
use InvalidArgumentException;

/**
 * Indicates that an outbox message contains invalid envelope data.
 */
final class InvalidOutboxMessageException extends InvalidArgumentException implements OutboxException
{
}
