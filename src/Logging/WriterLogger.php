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
     * Creates one record with the clock's current time and passes it to the writer.
     *
     * @param LogLevel $level The message severity.
     * @param string $message The diagnostic message.
     * @param array<string, mixed> $context Additional contextual data.
     *
     * @return void
     *
     * @throws LogTimestampException When the clock cannot provide a timestamp.
     * @throws LoggingException When the writer fails, propagated unchanged.
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
