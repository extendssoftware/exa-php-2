<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\JsonRpc\Method\Exception;

use ExtendsSoftware\ExaPHP\JsonRpc\JsonRpcException;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\ErrorDetails;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\ErrorResponse;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\Exception\InvalidMessageException;
use RuntimeException;
use Throwable;

/**
 * Reports an intentional method failure whose code, message, and optional data may be returned to the caller.
 */
final class MethodCallException extends RuntimeException implements JsonRpcException
{
    /**
     * Creates a public method error, preserving an optional underlying failure.
     *
     * @param int $code The JSON-RPC error code; use standard codes or application codes outside the reserved range.
     * @param string $message The public error message, without sensitive internal details.
     * @param mixed $data Additional JSON-encodable public error data, omitted when null.
     * @param Throwable|null $previous The underlying failure, never included in the response.
     */
    public function __construct(
        int $code,
        string $message,
        public readonly mixed $data = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Creates a JSON-RPC response exposing only the explicitly supplied public error details.
     *
     * @param float|int|string|null $id The request identifier.
     *
     * @return ErrorResponse The typed error response, ready for encoding.
     *
     * @throws InvalidMessageException When the identifier is non-finite or invalid UTF-8.
     */
    public function response(float|int|string|null $id): ErrorResponse
    {
        return new ErrorResponse($id, new ErrorDetails($this->getCode(), $this->getMessage(), $this->data));
    }
}
