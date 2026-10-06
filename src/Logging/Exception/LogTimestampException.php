<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Logging\Exception;

use ExtendsSoftware\ExaPHP\Logging\LoggingException;
use RuntimeException;

/**
 * Indicates that a log record could not receive a timestamp.
 */
final class LogTimestampException extends RuntimeException implements LoggingException
{
}
