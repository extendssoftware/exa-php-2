<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails;

use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\Message\ProtocolVersion;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Representation\Exception\ResponseEncodingException;
use ExtendsSoftware\ExaPHP\Http\Representation\JsonResponseFactory;

/**
 * Serializes problem details as application/problem+json with a matching HTTP status.
 */
final readonly class ProblemDetailsResponseFactory
{
    /**
     * Creates a non-cacheable problem response, replacing content type and removing stale framing headers.
     *
     * Problem responses use their dedicated media type independently of normal response content negotiation.
     *
     * @param ProblemDetails $problem The safe problem description.
     * @param Headers $headers Additional headers, such as Allow or WWW-Authenticate.
     * @param ProtocolVersion $protocolVersion The response protocol version.
     *
     * @return Response The encoded problem response.
     *
     * @throws ResponseEncodingException When problem data cannot be encoded as JSON.
     */
    public function create(
        ProblemDetails $problem,
        Headers $headers = new Headers(),
        ProtocolVersion $protocolVersion = ProtocolVersion::Http11,
    ): Response {
        $response = new JsonResponseFactory()->create($problem->toArray(), $problem->status, $headers);

        return $response->withHeaders(
            $response->headers->with('Content-Type', 'application/problem+json')->with('Cache-Control', 'no-store'),
        )->withProtocolVersion($protocolVersion);
    }
}
