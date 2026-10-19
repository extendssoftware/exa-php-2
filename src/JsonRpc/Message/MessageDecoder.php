<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\JsonRpc\Message;

use ExtendsSoftware\ExaPHP\JsonRpc\ErrorCode;
use JsonException;
use SensitiveParameter;
use stdClass;

use function array_is_list;
use function array_map;
use function array_values;
use function is_array;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function property_exists;

use const JSON_THROW_ON_ERROR;

/**
 * Decodes JSON-RPC 2.0 documents into validated envelopes and protocol error responses.
 */
final readonly class MessageDecoder
{
    /**
     * Decodes a single message or batch without executing methods.
     *
     * JSON objects remain distinct from positional arrays. Decoding uses PHP's depth limit of 512 and numeric
     * precision. Invalid documents and empty batches produce a single error response; invalid batch entries produce
     * error responses in their corresponding positions. Omitted parameters are represented by null.
     *
     * @param string $message The JSON document, potentially containing sensitive arguments.
     *
     * @return ErrorResponse|list<ErrorResponse|Notification|Request>|Notification|Request The decoded message or batch.
     */
    public function decode(#[SensitiveParameter] string $message): array|ErrorResponse|Notification|Request
    {
        try {
            $decoded = json_decode($message, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ErrorCode::ParseError->response();
        }
        if (!is_array($decoded)) {
            return $this->decodeEntry($decoded);
        }
        if ($decoded === []) {
            return ErrorCode::InvalidRequest->response();
        }

        return array_map($this->decodeEntry(...), array_values($decoded));
    }

    /**
     * Validates one decoded message and distinguishes requests from notifications by identifier presence.
     *
     * Invalid envelopes, including nested batch arrays, produce Invalid Request responses with a null identifier.
     *
     * @param mixed $message The decoded single message or batch entry.
     *
     * @return ErrorResponse|Notification|Request The validated message or its protocol error response.
     */
    private function decodeEntry(#[SensitiveParameter] mixed $message): ErrorResponse|Notification|Request
    {
        if (!$message instanceof stdClass || ($message->jsonrpc ?? null) !== '2.0'
            || !is_string($message->method ?? null)) {
            return ErrorCode::InvalidRequest->response();
        }
        $id = $message->id ?? null;
        if ($id !== null && !is_string($id) && !is_int($id) && !(is_float($id) && is_finite($id))) {
            return ErrorCode::InvalidRequest->response();
        }
        $params = null;
        if (property_exists($message, 'params')) {
            $params = $message->params;
            if (!$params instanceof stdClass && !(is_array($params) && array_is_list($params))) {
                return ErrorCode::InvalidRequest->response();
            }
        }

        return property_exists($message, 'id')
            ? new Request($id, $message->method, $params)
            : new Notification($message->method, $params);
    }
}
