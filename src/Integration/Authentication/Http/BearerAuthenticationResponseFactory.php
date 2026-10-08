<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Authentication\Http;

use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetails;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetailsResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Representation\Exception\ResponseEncodingException;
use Override;
use SensitiveParameter;

/**
 * Renders generic Bearer authentication failures as non-cacheable Problem Details responses.
 */
final readonly class BearerAuthenticationResponseFactory implements AuthenticationResponseFactory
{
    /**
     * Creates a Bearer challenge without exposing credential or verification details.
     *
     * @param AuthenticationFailure $failure The client failure category.
     * @param Request $request The request whose protocol version is preserved.
     *
     * @return Response A 400 response for malformed input or a 401 response for missing or rejected credentials.
     *
     * @throws ResponseEncodingException When the problem cannot be encoded.
     */
    #[Override]
    public function create(AuthenticationFailure $failure, #[SensitiveParameter] Request $request): Response
    {
        [$status, $title, $challenge] = match ($failure) {
            AuthenticationFailure::MissingCredentials => [
                StatusCode::Unauthorized, 'Unauthorized', 'Bearer realm="api"',
            ],
            AuthenticationFailure::MalformedCredentials => [
                StatusCode::BadRequest, 'Bad Request', 'Bearer realm="api", error="invalid_request"',
            ],
            AuthenticationFailure::RejectedCredentials => [
                StatusCode::Unauthorized, 'Unauthorized', 'Bearer realm="api", error="invalid_token"',
            ],
        };

        return new ProblemDetailsResponseFactory()->create(
            new ProblemDetails($status, $title),
            new Headers(['WWW-Authenticate' => $challenge]),
            $request->protocolVersion,
        );
    }
}
