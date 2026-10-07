<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Subscription\Exception;

use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use InvalidArgumentException;

/**
 * Indicates invalid subscriber identity or subscribed message types.
 */
final class InvalidSubscriptionException extends InvalidArgumentException implements MessagingException
{
}
