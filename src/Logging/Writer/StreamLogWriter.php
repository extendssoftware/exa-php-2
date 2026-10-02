<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Logging\Writer;

use Closure;
use ErrorException;
use ExtendsSoftware\ExaPHP\Logging\Exception\InvalidLogStreamException;
use ExtendsSoftware\ExaPHP\Logging\Exception\LogStreamOpenException;
use ExtendsSoftware\ExaPHP\Logging\Exception\LogStreamWriteException;
use ExtendsSoftware\ExaPHP\Logging\Formatter\JsonLogFormatter;
use ExtendsSoftware\ExaPHP\Logging\Formatter\LogFormatter;
use ExtendsSoftware\ExaPHP\Logging\LoggingException;
use ExtendsSoftware\ExaPHP\Logging\LogRecord;
use Throwable;
use ValueError;

use function fclose;
use function fflush;
use function flock;
use function fopen;
use function fseek;
use function fstat;
use function fwrite;
use function get_resource_type;
use function is_resource;
use function is_string;
use function restore_error_handler;
use function set_error_handler;
use function stream_get_meta_data;
use function str_contains;
use function strlen;
use function strpbrk;
use function substr;

use const E_NOTICE;
use const E_WARNING;
use const LOCK_EX;
use const LOCK_UN;
use const SEEK_END;

/**
 * Appends formatted records to paths, stream URIs, or caller-owned writable streams.
 *
 * Path destinations are opened and closed per record. Supplied streams remain open. Regular files use advisory
 * exclusive locks; other streams have no interprocess atomicity guarantee. Formatting determines record delimiters.
 */
final readonly class StreamLogWriter implements LogWriter
{
    /**
     * Creates a stream writer without opening path destinations.
     *
     * @param string|resource $destination A non-empty path or stream URI, or an open writable stream.
     * @param LogFormatter $formatter The record formatter, including any required delimiter.
     *
     * @throws InvalidLogStreamException When the destination is invalid, closed, or not writable.
     */
    public function __construct(
        private mixed $destination,
        private LogFormatter $formatter = new JsonLogFormatter(),
    ) {
        if (is_string($destination)) {
            if ($destination === '' || str_contains($destination, "\0")) {
                throw new InvalidLogStreamException('Log destination must be a non-empty path without null bytes.');
            }
        } else {
            $this->assertWritable($destination);
        }
    }

    /**
     * Formats once, appends all bytes, and flushes the stream.
     *
     * Seekable streams are positioned at their end. Positive partial writes are completed; zero progress is a failure.
     * Flush does not guarantee durable storage. Failures may leave a partially written record; no retry is performed.
     * Cleanup is attempted even after engine errors without replacing the original failure.
     *
     * @param LogRecord $record The record to deliver.
     *
     * @return void
     *
     * @throws InvalidLogStreamException When a caller-owned stream is no longer writable.
     * @throws LogStreamOpenException When the destination cannot be opened.
     * @throws LogStreamWriteException When writing, locking, flushing, or cleanup fails.
     * @throws LoggingException When formatting fails, propagated unchanged.
     */
    public function write(LogRecord $record): void
    {
        $data = $this->formatter->format($record);
        $owned = is_string($this->destination);
        $stream = $owned ? $this->openStream() : $this->destination;
        $locked = false;
        $failure = null;

        try {
            $this->assertWritable($stream);
            $metadata = stream_get_meta_data($stream);
            $this->io(function () use ($stream, $metadata, $data, &$locked): void {
                $stat = $metadata['stream_type'] === 'STDIO' ? fstat($stream) : false;
                if ($metadata['stream_type'] === 'STDIO' && $stat !== false
                    && ($stat['mode'] & 0170000) === 0100000) {
                    if (!flock($stream, LOCK_EX)) {
                        throw new LogStreamWriteException('Could not lock log stream.');
                    }
                    $locked = true;
                }
                if ($metadata['seekable'] && fseek($stream, 0, SEEK_END) !== 0) {
                    throw new LogStreamWriteException('Could not seek to the end of the log stream.');
                }
                $length = strlen($data);
                $offset = 0;
                while ($offset < $length) {
                    $written = fwrite($stream, substr($data, $offset));
                    if ($written === false || $written === 0) {
                        throw new LogStreamWriteException('Log stream write failed before the record was complete.');
                    }
                    $offset += $written;
                }
                if (!fflush($stream)) {
                    throw new LogStreamWriteException('Could not flush log stream.');
                }
            });
        } catch (Throwable $exception) {
            $failure = $exception;
        } finally {
            try {
                $this->io(static function () use ($stream, $owned, $locked): void {
                    try {
                        if ($locked && !flock($stream, LOCK_UN)) {
                            throw new LogStreamWriteException('Could not unlock log stream.');
                        }
                    } finally {
                        if ($owned && is_resource($stream) && !fclose($stream)) {
                            throw new LogStreamWriteException('Could not close log stream.');
                        }
                    }
                });
            } catch (Throwable $exception) {
                $failure ??= $exception;
            }
        }
        if ($failure !== null) {
            throw $failure;
        }
    }

    /**
     * Opens a destination in binary append mode.
     *
     * @return resource The owned stream.
     *
     * @throws LogStreamOpenException When opening fails.
     */
    private function openStream(): mixed
    {
        try {
            $stream = $this->io(fn() => fopen($this->destination, 'ab'));
        } catch (LogStreamWriteException $exception) {
            throw new LogStreamOpenException(
                'Could not open log destination.',
                0,
                $exception->getPrevious() ?? $exception,
            );
        }
        if ($stream === false) {
            throw new LogStreamOpenException('Could not open log destination.');
        }

        return $stream;
    }

    /**
     * Checks resource type and write mode without modifying the stream.
     *
     * @param mixed $stream The destination resource.
     *
     * @return void
     *
     * @throws InvalidLogStreamException When the resource is closed, not a stream, or read-only.
     */
    private function assertWritable(mixed $stream): void
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream'
            || strpbrk(stream_get_meta_data($stream)['mode'], 'waxc+') === false) {
            throw new InvalidLogStreamException('Log destination must be an open writable stream.');
        }
    }

    /**
     * Converts stream warnings, notices, and invalid-operation errors into logging failures.
     *
     * @param Closure(): mixed $operation The stream operation.
     *
     * @return mixed The operation result.
     *
     * @throws LogStreamWriteException When an operation raises a warning, notice, or value error.
     */
    private function io(Closure $operation): mixed
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }, E_WARNING | E_NOTICE);
        try {
            return $operation();
        } catch (ErrorException | ValueError $exception) {
            throw new LogStreamWriteException('Log stream operation failed.', 0, $exception);
        } finally {
            restore_error_handler();
        }
    }
}
