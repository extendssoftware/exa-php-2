<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Logging;

use ExtendsSoftware\ExaPHP\Clock\Clock;
use ExtendsSoftware\ExaPHP\Clock\ClockException;
use ExtendsSoftware\ExaPHP\Clock\SystemClock;
use ExtendsSoftware\ExaPHP\Logging\Exception\LogTimestampException;
use ExtendsSoftware\ExaPHP\Logging\Writer\LogWriter;
use Override;

/**
 * Creates a timestamped record for each message and submits it to a writer.
 */
final readonly class WriterLogger implements Logger
{
    /**
     * Creates a logger using the supplied writer and clock.
     *
     * @param LogWriter $writer The destination for records.
     * @param Clock $clock The timestamp source, defaulting to the system clock for standalone use.
     */
    public function __construct(private LogWriter $writer, private Clock $clock = new SystemClock())
    {
    }

    /**
     * {@inheritDoc}
     *
     * @throws LogTimestampException When the clock cannot provide a timestamp.
     */
    #[Override]
    public function log(LogLevel $level, string $message, array $context = []): void
    {
        try {
            $timestamp = $this->clock->now();
        } catch (ClockException $exception) {
            throw new LogTimestampException('Unable to timestamp the log record.', 0, $exception);
        }
        $this->writer->write(new LogRecord($level, $message, $timestamp, $context));
    }
}
