<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Outbox\Processing\Exception;

use ExtendsSoftware\ExaPHP\Outbox\OutboxException;
use RuntimeException;

/**
 * Indicates a persistence or time-source failure while claiming a message or recording its processing outcome.
 */
final class OutboxStoreException extends RuntimeException implements OutboxException
{
}
