<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Handler;

use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use Override;
use SensitiveParameter;

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
     * {@inheritDoc}
     */
    #[Override]
    public function handle(#[SensitiveParameter] Request $request): Response
    {
        return $this->resolver->resolve($this->handlerId)->handle($request);
    }
}
