<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Outbox\Delivery\Exception;

use ExtendsSoftware\ExaPHP\Outbox\OutboxException;
use RuntimeException;

/**
 * Indicates that outgoing message delivery could not be acknowledged.
 */
final class MessageDeliveryException extends RuntimeException implements OutboxException
{
}
