<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing;

use Override;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetails;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetailsResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Handler\HandlerResolver;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use Throwable;

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
     */
    public function __construct(private Router $router, private HandlerResolver $resolver)
    {
    }

    /**
     * Routes the request and supplies its RouteMatch as immutable request metadata.
     *
     * Existing RouteMatch metadata is replaced without changing the original request. Other attributes are retained.
     * A missing path produces 404; an unsupported method produces 405 with Allow.
     * Neither response resolves or invokes a handler.
     *
     * @param Request $request The incoming request.
     *
     * @return Response The selected handler's response, or a Problem Details routing error response.
     *
     * @throws Throwable When routing, handler resolution, or execution fails, propagated unchanged.
     */
    #[Override]
    public function handle(Request $request): Response
    {
        $match = $this->router->match($request);
        if ($match !== null) {
            return $this->resolver->resolve($match->route->handlerId)->handle($request->withAttribute($match));
        }
        $methods = $this->router->allowedMethods($request);
        if ($methods === []) {
            return new ProblemDetailsResponseFactory()->create(
                new ProblemDetails(StatusCode::NotFound, 'Not Found'),
                protocolVersion: $request->protocolVersion,
            );
        }
        $allow = implode(', ', array_map(static fn(Method $method): string => $method->value, $methods));

        return new ProblemDetailsResponseFactory()->create(
            new ProblemDetails(StatusCode::MethodNotAllowed, 'Method Not Allowed'),
            new Headers(['Allow' => $allow]),
            protocolVersion: $request->protocolVersion,
        );
    }
}
