<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Middleware;

use Override;
use ExtendsSoftware\ExaPHP\Http\Middleware\Exception\InvalidMiddlewareException;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use SensitiveParameter;

use function array_is_list;
use function array_reverse;

/**
 * Runs an ordered middleware chain around a final request handler.
 */
final readonly class MiddlewarePipeline implements RequestHandler
{
    /**
     * The composed handler chain, with the first middleware outermost.
     *
     * @var RequestHandler
     */
    private RequestHandler $handler;

    /**
     * Builds the chain without handling a request.
     *
     * @param RequestHandler $handler The final handler invoked when all middleware delegate.
     * @param list<Middleware> $middleware Middleware in request execution order; repeated instances run per occurrence.
     *
     * @throws InvalidMiddlewareException When middleware is not a list or contains an invalid entry.
     */
    public function __construct(RequestHandler $handler, array $middleware = [])
    {
        if (!array_is_list($middleware)) {
            throw new InvalidMiddlewareException('HTTP middleware must be a list.');
        }
        foreach ($middleware as $entry) {
            if (!$entry instanceof Middleware) {
                throw new InvalidMiddlewareException('Each HTTP middleware must implement Middleware.');
            }
        }
        foreach (array_reverse($middleware) as $entry) {
            $handler = new MiddlewareRequestHandler($entry, $handler);
        }
        $this->handler = $handler;
    }

    /**
     * {@inheritDoc}
     *
     * Each delegation starts the remaining chain afresh; repeated and nested calls have independent execution state.
     * Middleware instances are reused.
     */
    #[Override]
    public function handle(#[SensitiveParameter] Request $request): Response
    {
        return $this->handler->handle($request);
    }
}
