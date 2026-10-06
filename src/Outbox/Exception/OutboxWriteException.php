<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Outbox\Exception;

use ExtendsSoftware\ExaPHP\Outbox\OutboxException;
use RuntimeException;

/**
 * Indicates that a message could not be appended to the transactional outbox.
 */
final class OutboxWriteException extends RuntimeException implements OutboxException
{
}
