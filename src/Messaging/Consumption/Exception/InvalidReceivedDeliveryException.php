<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception;

use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use InvalidArgumentException;

/**
 * Indicates invalid subscriber identity, receipt, or attempt number for a received delivery.
 */
final class InvalidReceivedDeliveryException extends InvalidArgumentException implements MessagingException
{
}
