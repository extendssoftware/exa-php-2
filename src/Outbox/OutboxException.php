<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Outbox;

use Throwable;

/**
 * Identifies outbox message, delivery, retry, ownership, and persistence failures.
 */
interface OutboxException extends Throwable
{
}
