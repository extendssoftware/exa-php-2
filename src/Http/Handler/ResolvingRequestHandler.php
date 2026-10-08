<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Handler;

use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use Override;
use Throwable;

/**
 * Defers handler resolution until execution reaches the final handler.
 */
final readonly class ResolvingRequestHandler implements RequestHandler
{
    /**
     * Creates a deferred handler without resolving its service.
     *
     * @param HandlerResolver $resolver The handler resolver.
     * @param non-empty-string $handlerId The handler identifier to resolve on execution.
     */
    public function __construct(private HandlerResolver $resolver, private string $handlerId)
    {
    }

    /**
     * Resolves and executes the handler with the current request.
     *
     * @param Request $request The request after middleware processing.
     *
     * @return Response The handler response.
     *
     * @throws Throwable When resolution or execution fails.
     */
    #[Override]
    public function handle(Request $request): Response
    {
        return $this->resolver->resolve($this->handlerId)->handle($request);
    }
}
