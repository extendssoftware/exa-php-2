<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\JsonRpc\Message;

use ExtendsSoftware\ExaPHP\JsonRpc\Message\Exception\InvalidMessageException;
use SensitiveParameter;
use stdClass;

use function array_is_list;
use function is_array;
use function is_finite;
use function is_float;
use function is_string;
use function preg_match;

/**
 * Carries a validated request envelope with an explicit identifier.
 *
 * Fields cannot be replaced; nested argument objects and references retain their original mutability.
 */
final readonly class Request
{
    /**
     * Creates a request with positional, named, or omitted parameters.
     *
     * @param float|int|string|null $id The finite numeric, string, or explicit null identifier.
     * @param string $method The case-sensitive method name.
     * @param list<mixed>|stdClass|null $params Positional or named parameters, or null when omitted.
     *
     * @throws InvalidMessageException When the identifier is non-finite, envelope text is invalid UTF-8, or parameters
     *     are not a positional list or named object.
     */
    public function __construct(
        public float|int|string|null $id,
        public string $method,
        #[SensitiveParameter]
        public array|stdClass|null $params = null,
    ) {
        if (is_float($id) && !is_finite($id)) {
            throw new InvalidMessageException('Request identifiers must be finite.');
        }
        if ((is_string($id) && preg_match('//u', $id) !== 1) || preg_match('//u', $method) !== 1) {
            throw new InvalidMessageException('Request identifiers and method names require valid UTF-8.');
        }
        if (is_array($params) && !array_is_list($params)) {
            throw new InvalidMessageException(
                'Positional parameters must be a list; use an object for named parameters.',
            );
        }
    }
}
