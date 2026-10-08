<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Authentication\Http;

use ExtendsSoftware\ExaPHP\Authentication\Authenticator;
use ExtendsSoftware\ExaPHP\Authentication\Exception\InvalidCredentialsException;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Middleware\Middleware;
use ExtendsSoftware\ExaPHP\Integration\Authentication\Http\Exception\MalformedCredentialsException;
use Override;
use SensitiveParameter;
use Throwable;

/**
 * Requires authentication before delegating with the verified actor attached to the request.
 */
final readonly class AuthenticationMiddleware implements Middleware
{
    /**
     * Creates a required-authentication boundary.
     *
     * @param RequestCredentialsExtractor $extractor The configured credential scheme extractor.
     * @param Authenticator $authenticator The application credential verifier.
     * @param AuthenticationResponseFactory $responses The matching scheme's failure presenter.
     */
    public function __construct(
        private RequestCredentialsExtractor $extractor,
        private Authenticator $authenticator,
        private AuthenticationResponseFactory $responses,
    ) {
    }

    /**
     * Authenticates the request or returns a client authentication failure response.
     *
     * Existing actor metadata never bypasses verification and is replaced on success. Only extraction malformation
     * and credential rejection are handled locally. Operational, configuration, and downstream failures propagate.
     *
     * @param Request $request The request containing sensitive authentication data.
     * @param RequestHandler $next The remaining route pipeline.
     *
     * @return Response The authentication failure response or downstream response.
     *
     * @throws Throwable When verification, response creation, or downstream execution fails.
     */
    #[Override]
    public function process(#[SensitiveParameter] Request $request, RequestHandler $next): Response
    {
        try {
            $credentials = $this->extractor->extract($request);
        } catch (MalformedCredentialsException) {
            return $this->responses->create(AuthenticationFailure::MalformedCredentials, $request);
        }
        if ($credentials === null) {
            return $this->responses->create(AuthenticationFailure::MissingCredentials, $request);
        }
        try {
            $actor = $this->authenticator->authenticate($credentials);
        } catch (InvalidCredentialsException) {
            return $this->responses->create(AuthenticationFailure::RejectedCredentials, $request);
        }

        return $next->handle($request->withAttribute($actor));
    }
}
