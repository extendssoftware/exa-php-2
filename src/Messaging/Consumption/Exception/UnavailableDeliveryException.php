<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception;

use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use RuntimeException;

/**
 * Indicates that a delivery receipt does not identify a valid outstanding attempt for the consumer.
 */
final class UnavailableDeliveryException extends RuntimeException implements MessagingException
{
}
