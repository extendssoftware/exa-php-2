<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Exception;

use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use InvalidArgumentException;

/**
 * Indicates that a message contains invalid envelope data.
 */
final class InvalidMessageException extends InvalidArgumentException implements MessagingException
{
}
