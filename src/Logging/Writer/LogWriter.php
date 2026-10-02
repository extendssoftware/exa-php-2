<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Logging\Writer;

use ExtendsSoftware\ExaPHP\Logging\LoggingException;
use ExtendsSoftware\ExaPHP\Logging\LogRecord;

/**
 * Accepts existing log records for delivery without recreating them.
 */
interface LogWriter
{
    /**
     * Delivers a record without modifying it or its context objects.
     *
     * @param LogRecord $record The record to deliver.
     *
     * @return void
     *
     * @throws LoggingException When delivery fails.
     */
    public function write(LogRecord $record): void;
}
