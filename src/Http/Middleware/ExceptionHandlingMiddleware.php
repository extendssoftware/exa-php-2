<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Middleware;

use Override;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use Throwable;
use SensitiveParameter;

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
     * {@inheritDoc}
     *
     * The factory receives this middleware's request, not replacements made downstream. Factory failures propagate
     * without retry or fallback. Deferred body reads and response emission occur outside this boundary.
     */
    #[Override]
    public function process(#[SensitiveParameter] Request $request, RequestHandler $next): Response
    {
        try {
            return $next->handle($request);
        } catch (Throwable $exception) {
            return $this->factory->create($exception, $request);
        }
    }
}
