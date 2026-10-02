<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Logging\Formatter;

use ExtendsSoftware\ExaPHP\Logging\LoggingException;
use ExtendsSoftware\ExaPHP\Logging\LogRecord;

/**
 * Converts log records to text without writing to a destination.
 */
interface LogFormatter
{
    /**
     * Formats a record without modifying it or its context objects.
     *
     * @param LogRecord $record The record to format.
     *
     * @return string The formatted representation.
     *
     * @throws LoggingException When the record cannot be formatted.
     */
    public function format(LogRecord $record): string;
}
