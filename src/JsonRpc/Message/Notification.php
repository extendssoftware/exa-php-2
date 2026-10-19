<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\JsonRpc\Message;

use ExtendsSoftware\ExaPHP\JsonRpc\Message\Exception\InvalidMessageException;
use SensitiveParameter;
use stdClass;

use function array_is_list;
use function is_array;
use function preg_match;

/**
 * Carries a validated notification envelope without a request identifier.
 *
 * Fields cannot be replaced; nested argument objects and references retain their original mutability.
 */
final readonly class Notification
{
    /**
     * Creates a notification with positional, named, or omitted parameters.
     *
     * @param string $method The case-sensitive method name.
     * @param list<mixed>|stdClass|null $params Positional or named parameters, or null when omitted.
     *
     * @throws InvalidMessageException When the method name is invalid UTF-8 or positional parameters are not a list.
     */
    public function __construct(
        public string $method,
        #[SensitiveParameter]
        public array|stdClass|null $params = null,
    ) {
        if (preg_match('//u', $method) !== 1) {
            throw new InvalidMessageException('Notification method names require valid UTF-8.');
        }
        if (is_array($params) && !array_is_list($params)) {
            throw new InvalidMessageException(
                'Positional parameters must be a list; use an object for named parameters.',
            );
        }
    }
}
