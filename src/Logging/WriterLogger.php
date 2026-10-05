<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Logging;

use DateTimeImmutable;
use DateTimeZone;
use ExtendsSoftware\ExaPHP\Logging\Writer\LogWriter;
use Override;

/**
 * Creates a timestamped record for each message and submits it to a writer.
 */
final readonly class WriterLogger implements Logger
{
    /**
     * Creates a logger using the supplied writer.
     *
     * @param LogWriter $writer The destination for records.
     */
    public function __construct(private LogWriter $writer)
    {
    }

    /**
     * Creates one record with the current UTC time and passes it to the writer.
     *
     * @param LogLevel $level The message severity.
     * @param string $message The diagnostic message.
     * @param array<string, mixed> $context Additional contextual data.
     *
     * @return void
     *
     * @throws LoggingException When the writer fails, propagated unchanged.
     */
    #[Override]
    public function log(LogLevel $level, string $message, array $context = []): void
    {
        $timestamp = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->writer->write(new LogRecord($level, $message, $timestamp, $context));
    }
}
