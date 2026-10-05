<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cli\Output\Fixture;

use function strlen;
use function substr;

/**
 * Provides deterministic partial writes and zero-progress failures for stream output tests.
 */
final class PartialOutputStream
{
    /**
     * Stream context assigned by PHP's wrapper API.
     *
     * @var resource|null
     */
    public mixed $context = null;

    /**
     * Bytes accepted by the wrapper.
     *
     * @var string
     */
    public string $bytes = '';

    /**
     * Opens the test destination.
     *
     * @param string $path The wrapper URI.
     * @param string $mode The requested mode.
     * @param int $options Wrapper open flags.
     * @param string|null $openedPath Resolved path output.
     *
     * @return bool Whether opening succeeded.
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    /**
     * Accepts two bytes at a time until the special zero marker is encountered.
     *
     * @param string $data Bytes requested by the caller.
     *
     * @return int Bytes accepted, or zero when the marker is reached.
     */
    public function stream_write(string $data): int
    {
        if ($data[0] === '!') {
            return 0;
        }
        $part = substr($data, 0, 2);
        $this->bytes .= $part;

        return strlen($part);
    }

    /**
     * Reports that the output-only stream has no readable data.
     *
     * @return bool True.
     */
    public function stream_eof(): bool
    {
        return true;
    }
}
