<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Middleware;

use Override;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use SensitiveParameter;

/**
 * Connects one middleware to the remaining handler chain.
 *
 * @internal
 */
final readonly class MiddlewareRequestHandler implements RequestHandler
{
    /**
     * Creates a chain link without executing middleware.
     *
     * @param Middleware $middleware The middleware to execute.
     * @param RequestHandler $next The remaining handler chain.
     */
    public function __construct(private Middleware $middleware, private RequestHandler $next)
    {
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function handle(#[SensitiveParameter] Request $request): Response
    {
        return $this->middleware->process($request, $this->next);
    }
}
