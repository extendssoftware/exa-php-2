<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Http;

use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationMerger;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\Http\Handler\HandlerResolver;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use ExtendsSoftware\ExaPHP\Http\Middleware\Exception\MiddlewareResolutionException;
use ExtendsSoftware\ExaPHP\Http\Middleware\Middleware;
use ExtendsSoftware\ExaPHP\Http\Routing\Route;
use ExtendsSoftware\ExaPHP\Http\Routing\RouteCollection;
use ExtendsSoftware\ExaPHP\Http\Routing\RouteGroup;
use ExtendsSoftware\ExaPHP\Http\Routing\RouteMatch;
use ExtendsSoftware\ExaPHP\Http\Routing\RoutingRequestHandler;
use ExtendsSoftware\ExaPHP\Http\Routing\SimpleRouter;
use ExtendsSoftware\ExaPHP\Integration\Http\HttpModule;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;

final class RouteMiddlewareIntegrationTest extends TestCase
{
    public function testRunsGroupAndRouteMiddlewareInOrderWithMetadataAndDeferredHandlerResolution(): void
    {
        $calls = [];
        $metadata = new stdClass();
        $definitions = [];
        foreach (['outer', 'inner', 'route'] as $id) {
            $middleware = $this->createStub(Middleware::class);
            $middleware->method('process')->willReturnCallback(
                static function (Request $request, RequestHandler $next) use (&$calls, $id, $metadata): Response {
                    self::assertSame('42', $request->attributes->get(RouteMatch::class)->parameter('id'));
                    $calls[] = $id . '.before';
                    $response = $next->handle($request->withAttribute($metadata));
                    $calls[] = $id . '.after';

                    return $response;
                },
            );
            $definitions[$id] = new InstanceDefinition($middleware);
        }
        $handler = $this->createStub(RequestHandler::class);
        $handler->method('handle')->willReturnCallback(
            static function (Request $request) use (&$calls, $metadata): Response {
                self::assertSame($metadata, $request->attributes->get(stdClass::class));
                $calls[] = 'handler';

                return new Response(StatusCode::Accepted);
            },
        );
        $definitions['handler'] = new FactoryDefinition(static function () use (&$calls, $handler): RequestHandler {
            $calls[] = 'resolve';

            return $handler;
        });
        $routes = ['group' => new RouteGroup('/admin', ['outer'], [
            new RouteGroup('/articles', ['inner'], [new Route('article', Method::Get, '/{id}', 'handler', ['route'])]),
        ])];
        $services = $this->services($routes, $definitions);
        $pipeline = $services->get(RequestHandler::class);
        self::assertSame([], $calls);
        $request = new Request(Method::Get, new Uri('/admin/articles/42'));
        self::assertSame(StatusCode::Accepted, $pipeline->handle($request)->statusCode);
        self::assertSame([
            'outer.before', 'inner.before', 'route.before', 'resolve', 'handler',
            'route.after', 'inner.after', 'outer.after',
        ], $calls);
        self::assertFalse($request->attributes->has(stdClass::class));
    }

    public function testShortCircuitDoesNotResolveHandlerAndUnmatchedRoutesDoNotResolveMiddleware(): void
    {
        $middleware = $this->createMock(Middleware::class);
        $middleware->expects($this->once())->method('process')->willReturn(new Response(StatusCode::Forbidden));
        $services = $this->services([
            'protected' => new Route('protected', Method::Get, '/protected', 'missing-handler', ['deny']),
            'unused' => new Route('unused', Method::Get, '/unused', 'missing-handler', ['missing-middleware']),
        ], ['deny' => new InstanceDefinition($middleware)]);
        $pipeline = $services->get(RequestHandler::class);
        self::assertSame(StatusCode::Forbidden, $pipeline->handle(
            new Request(Method::Get, new Uri('/protected')),
        )->statusCode);
        self::assertSame(StatusCode::NotFound, $pipeline->handle(
            new Request(Method::Get, new Uri('/missing')),
        )->statusCode);
        self::assertSame(StatusCode::MethodNotAllowed, $pipeline->handle(
            new Request(Method::Post, new Uri('/unused')),
        )->statusCode);
    }

    public function testRouteFailuresReachTheGlobalExceptionBoundary(): void
    {
        $middleware = $this->createStub(Middleware::class);
        $middleware->method('process')->willThrowException(new TypeError('Private failure.'));
        $services = $this->services([
            'failure' => new Route('failure', Method::Get, '/failure', 'missing', ['failure']),
            'missing' => new Route('missing', Method::Get, '/missing', 'missing', ['missing']),
        ], ['failure' => new InstanceDefinition($middleware)]);
        foreach (['/failure', '/missing'] as $path) {
            self::assertSame(StatusCode::InternalServerError, $services->get(RequestHandler::class)->handle(
                new Request(Method::Get, new Uri($path)),
            )->statusCode);
        }
    }

    public function testMissingResolverNeverBypassesRouteMiddleware(): void
    {
        $resolver = $this->createMock(HandlerResolver::class);
        $resolver->expects($this->never())->method('resolve');
        $route = new Route('protected', Method::Get, '/', 'handler', ['auth']);
        $routing = new RoutingRequestHandler(new SimpleRouter(new RouteCollection([$route])), $resolver);
        $this->expectException(MiddlewareResolutionException::class);
        $routing->handle(new Request(Method::Get, new Uri('/')));
    }

    /** @param array<string, Route|RouteGroup> $routes @param array<string, object> $definitions */
    private function services(array $routes, array $definitions): ServiceLocator
    {
        $defaults = require new HttpModule()->configDirectory() . '/services.php';
        $configuration = new ConfigurationMerger()->merge(['http' => $defaults], ['application' => [
            'http' => ['routes' => $routes], 'services' => $definitions,
        ]]);

        return new ServiceLocatorFactory()->create($configuration);
    }
}
