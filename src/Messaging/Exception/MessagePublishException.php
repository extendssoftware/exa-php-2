<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Exception;

use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use RuntimeException;

/**
 * Indicates that durable acceptance of a published message could not be acknowledged.
 */
final class MessagePublishException extends RuntimeException implements MessagingException
{
}
