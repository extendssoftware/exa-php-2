<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Message\Body;

use Override;
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
     * {@inheritDoc}
     */
    #[Override]
    public function chunks(): iterable
    {
        if ($this->content !== '') {
            yield $this->content;
        }
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function size(): int
    {
        return strlen($this->content);
    }
}
