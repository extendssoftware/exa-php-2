<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Middleware;

use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Request;
use ExtendsSoftware\ExaPHP\Http\Response;
use Throwable;

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
     * Executes middleware with the remaining chain available for delegation.
     *
     * @param Request $request The incoming request.
     *
     * @return Response The middleware's response.
     *
     * @throws Throwable When execution fails, propagated unchanged.
     */
    public function handle(Request $request): Response
    {
        return $this->middleware->process($request, $this->next);
    }
}
