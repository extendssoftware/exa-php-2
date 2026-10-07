<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Subscription\Exception;

use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use InvalidArgumentException;

/**
 * Indicates malformed subscription registrations.
 */
final class InvalidSubscriptionRegistrationException extends InvalidArgumentException implements MessagingException
{
}
