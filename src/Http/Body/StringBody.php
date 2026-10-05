<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Body;

use function strlen;

/**
 * Provides immutable in-memory bytes with independent, repeatable iteration and no external resources.
 */
final readonly class StringBody implements Body
{
    /**
     * Creates an in-memory body without encoding or interpreting its bytes.
     *
     * @param string $content The content, empty by default.
     */
    public function __construct(private string $content = '')
    {
    }

    /**
     * Returns the complete stored byte string.
     *
     * @return string The content.
     */
    public function content(): string
    {
        return $this->content;
    }

    /**
     * Yields the complete content once, or no chunks when empty.
     *
     * @return iterable<string> The stored content as a byte chunk.
     */
    public function chunks(): iterable
    {
        if ($this->content !== '') {
            yield $this->content;
        }
    }

    /**
     * Returns the length in bytes.
     *
     * @return int<0, max> The byte length.
     */
    public function size(): int
    {
        return strlen($this->content);
    }
}
