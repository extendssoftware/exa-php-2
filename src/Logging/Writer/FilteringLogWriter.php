<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Logging\Writer;

use Override;
use Closure;
use ExtendsSoftware\ExaPHP\Logging\LoggingException;
use ExtendsSoftware\ExaPHP\Logging\LogRecord;
use Throwable;

/**
 * Forwards records accepted by a predicate to another writer.
 */
final readonly class FilteringLogWriter implements LogWriter
{
    /**
     * Determines whether a record should be forwarded.
     *
     * @var Closure(LogRecord): bool
     */
    private Closure $predicate;

    /**
     * Creates a writer with a record predicate.
     *
     * @param LogWriter $writer The writer receiving accepted records.
     * @param callable(LogRecord): bool $predicate A predicate that must not modify the record or its context objects.
     */
    public function __construct(private LogWriter $writer, callable $predicate)
    {
        $this->predicate = $predicate(...);
    }

    /**
     * Evaluates the predicate once and forwards the original record when accepted.
     *
     * Rejected records are silently discarded. Predicate and writer failures propagate unchanged.
     *
     * @param LogRecord $record The record to evaluate.
     *
     * @return void
     *
     * @throws LoggingException When the wrapped writer fails.
     * @throws Throwable When the application-provided predicate fails.
     */
    #[Override]
    public function write(LogRecord $record): void
    {
        if (!($this->predicate)($record)) {
            return;
        }

        $this->writer->write($record);
    }
}
