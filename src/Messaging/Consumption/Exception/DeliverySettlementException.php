<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception;

use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use RuntimeException;

/**
 * Indicates failure to acknowledge, retry, or reject a subscriber delivery.
 */
final class DeliverySettlementException extends RuntimeException implements MessagingException
{
}
