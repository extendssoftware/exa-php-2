<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Logging\Exception;

use ExtendsSoftware\ExaPHP\Logging\LoggingException;
use RuntimeException;

/**
 * Indicates that a logging destination could not be opened.
 */
final class LogStreamOpenException extends RuntimeException implements LoggingException
{
}
