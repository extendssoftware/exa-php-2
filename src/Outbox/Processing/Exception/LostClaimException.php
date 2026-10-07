<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Outbox\Processing\Exception;

use ExtendsSoftware\ExaPHP\Outbox\OutboxException;
use RuntimeException;

/**
 * Indicates that a processing claim no longer owns its message.
 */
final class LostClaimException extends RuntimeException implements OutboxException
{
}
