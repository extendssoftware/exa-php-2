<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\JsonRpc;

use ExtendsSoftware\ExaPHP\JsonRpc\Message\ErrorDetails;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\ErrorResponse;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\Exception\InvalidMessageException;

/**
 * Identifies the standard JSON-RPC 2.0 protocol errors.
 */
enum ErrorCode: int
{
    /**
     * The input could not be decoded as JSON.
     */
    case ParseError = -32700;

    /**
     * The input is not a valid request object.
     */
    case InvalidRequest = -32600;

    /**
     * The requested method is unavailable.
     */
    case MethodNotFound = -32601;

    /**
     * The method parameters are invalid.
     */
    case InvalidParams = -32602;

    /**
     * An unexpected failure prevented method execution or response encoding.
     */
    case InternalError = -32603;

    /**
     * Creates a JSON-RPC error response with the standard message.
     *
     * @param float|int|string|null $id The request identifier, or null when unavailable.
     * @param mixed $data Additional JSON-encodable public error data, omitted when null.
     *
     * @return ErrorResponse The typed error response, ready for encoding.
     *
     * @throws InvalidMessageException When the identifier is non-finite or invalid UTF-8.
     */
    public function response(float|int|string|null $id = null, mixed $data = null): ErrorResponse
    {
        return new ErrorResponse($id, new ErrorDetails(
            $this->value,
            match ($this) {
                self::ParseError => 'Parse error',
                self::InvalidRequest => 'Invalid Request',
                self::MethodNotFound => 'Method not found',
                self::InvalidParams => 'Invalid params',
                self::InternalError => 'Internal error',
            },
            $data,
        ));
    }
}
