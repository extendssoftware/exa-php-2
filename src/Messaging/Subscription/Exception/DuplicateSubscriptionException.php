<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Subscription\Exception;

use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use InvalidArgumentException;

/**
 * Indicates that a subscriber identifier is registered more than once.
 */
final class DuplicateSubscriptionException extends InvalidArgumentException implements MessagingException
{
}
