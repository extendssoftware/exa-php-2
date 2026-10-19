<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\JsonRpc\Message;

use ExtendsSoftware\ExaPHP\JsonRpc\ErrorCode;
use SensitiveParameter;
use stdClass;
use Throwable;

use function implode;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/**
 * Encodes typed responses without retrying application serializers.
 */
final readonly class MessageEncoder
{
    /**
     * Encodes a result or error response as a JSON-RPC 2.0 document.
     *
     * Result values and error data follow PHP's JSON serialization rules. Serialization exceptions and engine errors
     * become generic internal errors with the original request identifier. Application serializers are not retried.
     *
     * @param ErrorResponse|ResultResponse $response The response to encode.
     *
     * @return string The encoded response, or its replacement internal error.
     */
    public function encode(#[SensitiveParameter] ErrorResponse|ResultResponse $response): string
    {
        try {
            return json_encode($this->toObject($response), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (Throwable) {
            return json_encode(
                $this->toObject(ErrorCode::InternalError->response($response->id)),
                JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
            );
        }
    }

    /**
     * Encodes batch responses in iteration order, consuming the source once.
     *
     * Each response is encoded before requesting the next item, allowing lazy execution to preserve serialization
     * order. An individual encoding failure does not prevent subsequent responses from being consumed.
     * Failures from the source iterable propagate unchanged.
     *
     * @param iterable<ErrorResponse|ResultResponse> $responses The response source, excluding notifications.
     *
     * @return string|null The encoded response array, or null when the source yields no responses.
     *
     * @throws Throwable When the source iterable fails.
     */
    public function encodeBatch(#[SensitiveParameter] iterable $responses): ?string
    {
        $encoded = [];
        foreach ($responses as $response) {
            $encoded[] = $this->encode($response);
        }

        return $encoded === [] ? null : '[' . implode(',', $encoded) . ']';
    }

    /**
     * Builds the JSON-RPC wire representation without serializing application values.
     *
     * The object contains either a result or an error, preserving the identifier and omitting null error data.
     *
     * @param ErrorResponse|ResultResponse $response The typed response to represent.
     *
     * @return stdClass The response object ready for JSON encoding.
     */
    private function toObject(#[SensitiveParameter] ErrorResponse|ResultResponse $response): stdClass
    {
        if ($response instanceof ResultResponse) {
            return (object) ['jsonrpc' => '2.0', 'result' => $response->result, 'id' => $response->id];
        }
        $error = (object) ['code' => $response->error->code, 'message' => $response->error->message];
        if ($response->error->data !== null) {
            $error->data = $response->error->data;
        }

        return (object) ['jsonrpc' => '2.0', 'error' => $error, 'id' => $response->id];
    }
}
