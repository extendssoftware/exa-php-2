<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Logging\Writer;

use Override;
use ExtendsSoftware\ExaPHP\Logging\Writer\Exception\CompositeLogWriteException;
use ExtendsSoftware\ExaPHP\Logging\LoggingException;
use ExtendsSoftware\ExaPHP\Logging\LogRecord;
use Throwable;

use function array_values;

/**
 * Delivers the same record to writers in registration order, collecting logging failures.
 */
final readonly class CompositeLogWriter implements LogWriter
{
    /**
     * The writers in delivery order.
     *
     * @var list<LogWriter>
     */
    private array $writers;

    /**
     * Creates a composite with zero or more writers.
     *
     * @param LogWriter ...$writers Writers in delivery order; repeated instances are invoked for each occurrence.
     */
    public function __construct(LogWriter ...$writers)
    {
        $this->writers = array_values($writers);
    }

    /**
     * {@inheritDoc}
     *
     * An empty composite discards the record. Failures outside `LoggingException` stop delivery immediately.
     * Successful deliveries are not rolled back and failed deliveries are not retried.
     *
     * @throws CompositeLogWriteException When any writers report logging failures.
     * @throws Throwable When a writer throws a failure outside LoggingException.
     */
    #[Override]
    public function write(LogRecord $record): void
    {
        $failures = [];
        foreach ($this->writers as $index => $writer) {
            try {
                $writer->write($record);
            } catch (LoggingException $exception) {
                $failures[$index] = $exception;
            }
        }

        if ($failures !== []) {
            throw new CompositeLogWriteException($failures);
        }
    }
}
