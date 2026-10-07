<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception;

use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use RuntimeException;

/**
 * Indicates failure to receive a subscriber delivery.
 */
final class MessageReceiveException extends RuntimeException implements MessagingException
{
}
