<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Logging\Writer\Exception;

use ExtendsSoftware\ExaPHP\Logging\LoggingException;
use RuntimeException;

/**
 * Indicates a stream write, flush, lock, or cleanup failure.
 */
final class LogStreamWriteException extends RuntimeException implements LoggingException
{
}
