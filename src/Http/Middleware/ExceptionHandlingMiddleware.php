<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Middleware;

use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use Throwable;

/**
 * Converts downstream exceptions and engine errors into responses through an injected factory.
 */
final readonly class ExceptionHandlingMiddleware implements Middleware
{
    /**
     * Creates an exception boundary using the supplied response policy.
     *
     * @param ExceptionResponseFactory $factory The factory responsible for representing failures.
     */
    public function __construct(private ExceptionResponseFactory $factory)
    {
    }

    /**
     * Delegates once and converts any thrown failure into a response.
     *
     * The factory receives this middleware's request, not replacements made downstream. Factory failures propagate
     * unchanged without retry or fallback. Deferred body reads and response emission occur outside this boundary.
     *
     * @param Request $request The incoming request.
     * @param RequestHandler $next The downstream handler.
     *
     * @return Response The downstream response or the factory's failure response.
     *
     * @throws Throwable When the exception response factory fails, propagated unchanged.
     */
    public function process(Request $request, RequestHandler $next): Response
    {
        try {
            return $next->handle($request);
        } catch (Throwable $exception) {
            return $this->factory->create($exception, $request);
        }
    }
}
