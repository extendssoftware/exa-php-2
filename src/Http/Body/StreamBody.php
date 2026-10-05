<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Body;

use ErrorException;
use ExtendsSoftware\ExaPHP\Http\Exception\BodyReadException;
use ExtendsSoftware\ExaPHP\Http\Exception\InvalidBodyStreamException;

use function feof;
use function fread;
use function get_resource_type;
use function is_resource;
use function restore_error_handler;
use function set_error_handler;
use function stream_get_meta_data;
use function strpbrk;

use const E_WARNING;

/**
 * Consumes a borrowed readable stream once, starting at its current position.
 *
 * The caller must not read, seek, or close the stream during consumption. This body never rewinds or closes it.
 * Even partial or failed iteration prevents replay. The stream must make progress until EOF; nonblocking polling
 * is not supported. No total length is inferred from mutable stream metadata.
 */
final class StreamBody implements Body
{
    /**
     * Whether iteration has started, including an abandoned or failed iteration.
     *
     * @var bool
     */
    private bool $started = false;

    /**
     * Creates a body without consuming its stream.
     *
     * @param resource $stream The borrowed readable stream.
     *
     * @throws InvalidBodyStreamException When the value is not an open readable stream.
     */
    public function __construct(private readonly mixed $stream)
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream'
            || strpbrk(stream_get_meta_data($stream)['mode'], 'r+') === false) {
            throw new InvalidBodyStreamException('Body requires an open readable stream.');
        }
    }

    /**
     * Reads from the current position in chunks of at most 8192 bytes.
     *
     * @return iterable<string> Non-empty byte chunks.
     *
     * @throws BodyReadException When iteration is repeated, the stream is closed, or reading fails to make progress.
     */
    public function chunks(): iterable
    {
        if ($this->started) {
            throw new BodyReadException('Stream bodies can only be consumed once.');
        }
        $this->started = true;
        while (true) {
            $chunk = $this->read();
            if ($chunk === '') {
                return;
            }
            yield $chunk;
        }
    }

    /**
     * Returns an unknown length without inspecting or consuming the stream.
     *
     * @return null Stream body lengths are not inferred.
     */
    public function size(): ?int
    {
        return null;
    }

    /**
     * Reads a chunk while translating native stream warnings.
     *
     * @return string Bytes, or an empty string at EOF.
     *
     * @throws BodyReadException When the stream is closed or reading fails.
     */
    private function read(): string
    {
        if (!is_resource($this->stream)) {
            throw new BodyReadException('Body stream is closed.');
        }
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }, E_WARNING);
        try {
            $chunk = fread($this->stream, 8192);
            if ($chunk === false || ($chunk === '' && !feof($this->stream))) {
                throw new BodyReadException('Body stream read failed to make progress.');
            }

            return $chunk;
        } catch (ErrorException $exception) {
            throw new BodyReadException('Could not read body stream.', 0, $exception);
        } finally {
            restore_error_handler();
        }
    }
}
