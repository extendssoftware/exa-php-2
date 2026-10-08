<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing;

use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetails;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetailsResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Handler\HandlerResolver;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Handler\ResolvingRequestHandler;
use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Middleware\Exception\MiddlewareResolutionException;
use ExtendsSoftware\ExaPHP\Http\Middleware\MiddlewarePipeline;
use ExtendsSoftware\ExaPHP\Http\Middleware\MiddlewareResolver;
use Override;
use SensitiveParameter;

use function array_map;
use function implode;

/**
 * Dispatches matched routes with typed metadata or returns Problem Details 404 and 405 responses.
 */
final readonly class RoutingRequestHandler implements RequestHandler
{
    /**
     * Creates a handler using the supplied router.
     *
     * @param Router $router The route selector.
     * @param HandlerResolver $resolver The resolver for the selected handler.
     * @param MiddlewareResolver|null $middlewareResolver Required when a matched route declares middleware.
     */
    public function __construct(
        private Router $router,
        private HandlerResolver $resolver,
        private ?MiddlewareResolver $middlewareResolver = null,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * Replaces existing `RouteMatch` metadata while retaining other attributes. A 405 response includes `Allow`.
     * Routing error responses do not resolve middleware or handlers. Matched middleware runs in declaration order;
     * the handler is resolved only when reached. Missing middleware resolution fails.
     */
    #[Override]
    public function handle(#[SensitiveParameter] Request $request): Response
    {
        $match = $this->router->match($request);
        if ($match !== null) {
            $middleware = [];
            foreach ($match->route->middleware as $id) {
                if ($this->middlewareResolver === null) {
                    throw new MiddlewareResolutionException('Route middleware requires a middleware resolver.');
                }
                $middleware[] = $this->middlewareResolver->resolve($id);
            }

            return new MiddlewarePipeline(
                new ResolvingRequestHandler($this->resolver, $match->route->handlerId), $middleware,
            )->handle($request->withAttribute($match));
        }
        $methods = $this->router->allowedMethods($request);
        if ($methods === []) {
            return new ProblemDetailsResponseFactory()->create(
                new ProblemDetails(StatusCode::NotFound, 'Not Found'),
                protocolVersion: $request->protocolVersion,
            );
        }
        $allow = implode(', ', array_map(static fn (Method $method): string => $method->value, $methods));

        return new ProblemDetailsResponseFactory()->create(
            new ProblemDetails(StatusCode::MethodNotAllowed, 'Method Not Allowed'),
            new Headers(['Allow' => $allow]),
            protocolVersion: $request->protocolVersion,
        );
    }
}
