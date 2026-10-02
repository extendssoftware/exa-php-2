<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Logging\Exception;

use ExtendsSoftware\ExaPHP\Logging\LoggingException;
use RuntimeException;

/**
 * Indicates that a log record cannot be represented in the requested format.
 */
final class LogFormattingException extends RuntimeException implements LoggingException
{
}
