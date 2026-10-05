<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Logging\Writer\Exception;

use ExtendsSoftware\ExaPHP\Logging\LoggingException;
use InvalidArgumentException;

/**
 * Indicates an invalid or non-writable logging destination.
 */
final class InvalidLogStreamException extends InvalidArgumentException implements LoggingException
{
}
