<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\JsonRpc\Message;

/**
 * Carries a public JSON-RPC error code, message, and optional data.
 *
 * Fields cannot be replaced; data objects and references retain their original mutability and serialization behavior.
 */
final readonly class ErrorDetails
{
    /**
     * Creates standard or application-defined error details.
     *
     * @param int $code The JSON-RPC error code.
     * @param string $message The public error message.
     * @param mixed $data Additional public error data, omitted from the wire representation when null.
     */
    public function __construct(public int $code, public string $message, public mixed $data = null) {}
}
