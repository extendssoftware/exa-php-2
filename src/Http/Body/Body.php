<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Body;

use ExtendsSoftware\ExaPHP\Http\HttpException;

/**
 * Provides HTTP body bytes without requiring the entire content to be buffered.
 */
interface Body
{
    /**
     * Returns body bytes in order, without HTTP transfer framing.
     *
     * Iteration may consume the body. Repeated or concurrent iteration is not guaranteed; implementations must document
     * replay and resource ownership and report unsupported reads as HTTP failures rather than silently losing content.
     *
     * @return iterable<string> Non-empty byte chunks; an empty body yields no chunks.
     *
     * @throws HttpException When reading fails, including failures raised during iteration.
     */
    public function chunks(): iterable;

    /**
     * Returns the total byte length without consuming the body.
     *
     * @return int<0, max>|null The total length, or null when unknown.
     *
     * @throws HttpException When the size cannot be inspected.
     */
    public function size(): ?int;
}
