<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Output;

use ErrorException;
use ExtendsSoftware\ExaPHP\Cli\Output\Exception\InvalidOutputStreamException;
use ExtendsSoftware\ExaPHP\Cli\Output\Exception\OutputWriteException;
use Override;
use ValueError;

use function fwrite;
use function get_resource_type;
use function is_resource;
use function restore_error_handler;
use function set_error_handler;
use function stream_get_meta_data;
use function strlen;
use function strpbrk;
use function substr;

use const E_NOTICE;
use const E_WARNING;

/**
 * Writes raw CLI output to borrowed streams without adding newlines, seeking, flushing, or closing them.
 *
 * Streams are written at their current positions. Partial writes are completed; zero progress fails rather than
 * polling a nonblocking stream. Concurrent writers have no atomicity guarantee. Callers control stream lifetime.
 */
final readonly class StreamOutput implements Output
{
    /**
     * Creates output channels without writing to either stream.
     *
     * @param resource $stdout The borrowed writable standard output stream.
     * @param resource $stderr The borrowed writable standard error stream, which may be the same stream.
     *
     * @throws InvalidOutputStreamException When either destination is closed, not a stream, or not writable.
     */
    public function __construct(private mixed $stdout, private mixed $stderr)
    {
        $this->assertWritable($stdout, 'stdout');
        $this->assertWritable($stderr, 'stderr');
    }

    /**
     * Writes all supplied bytes to the standard output stream.
     *
     * @param string $message The bytes to write without added delimiters.
     *
     * @return void
     *
     * @throws InvalidOutputStreamException When stdout is no longer an open writable stream.
     * @throws OutputWriteException When writing fails; some bytes may already have been delivered.
     */
    #[Override]
    public function write(string $message): void
    {
        $this->writeStream($this->stdout, $message, 'stdout');
    }

    /**
     * Writes all supplied bytes to the standard error stream.
     *
     * @param string $message The bytes to write without added delimiters.
     *
     * @return void
     *
     * @throws InvalidOutputStreamException When stderr is no longer an open writable stream.
     * @throws OutputWriteException When writing fails; some bytes may already have been delivered.
     */
    #[Override]
    public function writeError(string $message): void
    {
        $this->writeStream($this->stderr, $message, 'stderr');
    }

    /**
     * Validates the destination and completes positive partial writes.
     *
     * @param mixed $stream The borrowed destination.
     * @param string $message The bytes to write.
     * @param string $channel The channel name used in failure messages.
     *
     * @return void
     *
     * @throws InvalidOutputStreamException When the stream is invalid.
     * @throws OutputWriteException When writing returns false, makes no progress, or raises a native I/O failure.
     */
    private function writeStream(mixed $stream, string $message, string $channel): void
    {
        $this->assertWritable($stream, $channel);
        if ($message === '') {
            return;
        }
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }, E_WARNING | E_NOTICE);
        try {
            $length = strlen($message);
            $offset = 0;
            while ($offset < $length) {
                $written = fwrite($stream, substr($message, $offset));
                if ($written === false || $written === 0) {
                    throw new OutputWriteException('Could not complete CLI output to ' . $channel . '.');
                }
                $offset += $written;
            }
        } catch (ErrorException | ValueError $exception) {
            throw new OutputWriteException('CLI output write failed for ' . $channel . '.', 0, $exception);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Checks stream type and write mode without changing the destination.
     *
     * @param mixed $stream The destination value.
     * @param string $channel The channel name used in failure messages.
     *
     * @return void
     *
     * @throws InvalidOutputStreamException When the value is not an open writable stream.
     */
    private function assertWritable(mixed $stream, string $channel): void
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream'
            || strpbrk(stream_get_meta_data($stream)['mode'], 'waxc+') === false) {
            throw new InvalidOutputStreamException('CLI ' . $channel . ' must be an open writable stream.');
        }
    }
}
