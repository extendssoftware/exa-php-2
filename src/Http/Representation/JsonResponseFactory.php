<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Representation;

use ExtendsSoftware\ExaPHP\Http\Message\Body\StringBody;
use ExtendsSoftware\ExaPHP\Http\Representation\Exception\ResponseEncodingException;
use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use JsonException;
use Throwable;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Encodes representation data as application/json without partial output on encoding errors.
 */
final readonly class JsonResponseFactory implements ResponseFactory
{
    /**
     * Creates a JSON response, replacing content type and removing caller-supplied transfer and length headers.
     *
     * @param mixed $data JSON-encodable data; objects follow PHP's JSON serialization rules.
     * @param StatusCode $statusCode The response status.
     * @param Headers $headers Additional response headers.
     *
     * @return Response The JSON response.
     *
     * @throws ResponseEncodingException When JSON encoding fails, preserving the JsonException as its cause.
     * @throws Throwable When application serialization callbacks fail, propagated unchanged.
     */
    public function create(
        mixed $data,
        StatusCode $statusCode = StatusCode::Ok,
        Headers $headers = new Headers(),
    ): Response {
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ResponseEncodingException('Could not encode the JSON response.', 0, $exception);
        }

        return new Response(
            $statusCode,
            $headers->without('Content-Length')->without('Transfer-Encoding')->with('Content-Type', 'application/json'),
            new StringBody($json),
        );
    }
}
