<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry\Exception;

use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use InvalidArgumentException;

/**
 * Indicates invalid retry policy configuration, input, or delay.
 */
final class InvalidRetryPolicyException extends InvalidArgumentException implements MessagingException
{
}
