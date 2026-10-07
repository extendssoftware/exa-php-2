<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging;

use Throwable;

/**
 * Identifies message validation, publishing, subscription, resolution, and consumption failures.
 */
interface MessagingException extends Throwable
{
}
