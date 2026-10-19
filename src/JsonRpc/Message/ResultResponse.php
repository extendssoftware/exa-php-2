<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\JsonRpc\Message;

use ExtendsSoftware\ExaPHP\JsonRpc\Message\Exception\InvalidMessageException;
use SensitiveParameter;

use function is_finite;
use function is_float;
use function is_string;
use function preg_match;

/**
 * Carries a request identifier and an application result without serializing it.
 *
 * Fields cannot be replaced; result objects and references retain their original mutability and serialization behavior.
 */
final readonly class ResultResponse
{
    /**
     * Creates a successful response envelope.
     *
     * @param float|int|string|null $id The corresponding request identifier.
     * @param mixed $result The application result, validated for JSON encoding by the encoder.
     *
     * @throws InvalidMessageException When the identifier is non-finite or invalid UTF-8.
     */
    public function __construct(public float|int|string|null $id, #[SensitiveParameter] public mixed $result)
    {
        if (is_float($id) && !is_finite($id)) {
            throw new InvalidMessageException('Response identifiers must be finite.');
        }
        if (is_string($id) && preg_match('//u', $id) !== 1) {
            throw new InvalidMessageException('Response identifiers require valid UTF-8.');
        }
    }
}
