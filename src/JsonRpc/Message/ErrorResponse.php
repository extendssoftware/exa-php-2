<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\JsonRpc\Message;

use ExtendsSoftware\ExaPHP\JsonRpc\Message\Exception\InvalidMessageException;

use function is_finite;
use function is_float;
use function is_string;
use function preg_match;

/**
 * Carries a request identifier and public error details.
 */
final readonly class ErrorResponse
{
    /**
     * Creates an error response envelope.
     *
     * @param float|int|string|null $id The request identifier, or null when unavailable.
     * @param ErrorDetails $error The public error details.
     *
     * @throws InvalidMessageException When the identifier is non-finite or invalid UTF-8.
     */
    public function __construct(public float|int|string|null $id, public ErrorDetails $error)
    {
        if (is_float($id) && !is_finite($id)) {
            throw new InvalidMessageException('Response identifiers must be finite.');
        }
        if (is_string($id) && preg_match('//u', $id) !== 1) {
            throw new InvalidMessageException('Response identifiers require valid UTF-8.');
        }
    }
}
