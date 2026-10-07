<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Subscription\Exception;

use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use RuntimeException;

/**
 * Indicates that a subscriber identifier could not be resolved.
 */
final class SubscriberResolutionException extends RuntimeException implements MessagingException
{
}
