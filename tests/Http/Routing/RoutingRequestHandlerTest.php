<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Routing;

use ExtendsSoftware\ExaPHP\Http\Handler\Exception\HandlerResolutionException;
use ExtendsSoftware\ExaPHP\Http\Handler\HandlerResolver;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\ProtocolVersion;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use ExtendsSoftware\ExaPHP\Http\Routing\Route;
use ExtendsSoftware\ExaPHP\Http\Routing\RouteCollection;
use ExtendsSoftware\ExaPHP\Http\Routing\RouteMatch;
use ExtendsSoftware\ExaPHP\Http\Routing\Router;
use ExtendsSoftware\ExaPHP\Http\Routing\RoutingRequestHandler;
use ExtendsSoftware\ExaPHP\Http\Routing\SimpleRouter;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;
use TypeError;

use function implode;
use function iterator_to_array;
use function json_decode;

final class RoutingRequestHandlerTest extends TestCase
{
    public function testDispatchesWithMatchAndPreservesOtherMetadataAndOriginalRequest(): void
    {
        $metadata = new stdClass();
        $request = new Request(Method::Get, new Uri('/articles/42'))->withAttribute($metadata);
        $response = new Response(StatusCode::Accepted);
        $handler = $this->createMock(RequestHandler::class);
        $route = new Route('route.1', Method::Get, '/articles/{id}', 'handler');
        $handler->expects(self::once())->method('handle')->willReturnCallback(
            function (Request $routed) use ($request, $route, $metadata, $response): Response {
                $this->assertNotSame($request, $routed);
                $this->assertSame($metadata, $routed->attributes->get(stdClass::class));
                $this->assertSame($request->body, $routed->body);
                $match = $routed->attributes->get(RouteMatch::class);
                $this->assertSame($route, $match->route);
                $this->assertSame('42', $match->parameter('id'));

                return $response;
            },
        );
        $routing = new RoutingRequestHandler(new SimpleRouter(new RouteCollection([$route])), $this->resolver($handler));
        self::assertSame($response, $routing->handle($request));
        self::assertFalse($request->attributes->has(RouteMatch::class));
    }

    public function testExistingMatchIsReplacedOnlyOnRoutedRequest(): void
    {
        $handler = $this->createMock(RequestHandler::class);
        $route = new Route('route.2', Method::Get, '/{id}', 'handler');
        $oldMatch = new RouteMatch($route, ['id' => 'old']);
        $request = new Request(Method::Get, new Uri('/new'))->withAttribute($oldMatch);
        $handler->expects(self::once())->method('handle')->willReturnCallback(function (Request $routed): Response {
            $this->assertSame('new', $routed->attributes->get(RouteMatch::class)->parameter('id'));

            return new Response();
        });
        new RoutingRequestHandler(new SimpleRouter(new RouteCollection([$route])), $this->resolver($handler))->handle($request);
        self::assertSame($oldMatch, $request->attributes->get(RouteMatch::class));
    }

    public function testReturns404And405WithoutInvokingHandlers(): void
    {
        $resolver = $this->createMock(HandlerResolver::class);
        $resolver->expects(self::never())->method('resolve');
        $routing = new RoutingRequestHandler(new SimpleRouter(new RouteCollection([
            new Route('route.3', Method::Get, '/articles', 'handler'),
            new Route('route.4', Method::Post, '/articles', 'handler'),
        ])), $resolver);
        $missing = $routing->handle(new Request(Method::Get, new Uri('/missing')));
        self::assertSame(StatusCode::NotFound, $missing->statusCode);
        self::assertSame([], $missing->headers->get('Allow'));
        self::assertSame(['application/problem+json'], $missing->headers->get('Content-Type'));
        self::assertSame(404, json_decode(implode('', iterator_to_array($missing->body->chunks())), true)['status']);
        $notAllowed = $routing->handle(new Request(
            Method::Delete,
            new Uri('/articles'),
            protocolVersion: ProtocolVersion::Http2,
        ));
        self::assertSame(StatusCode::MethodNotAllowed, $notAllowed->statusCode);
        self::assertSame(['GET, POST'], $notAllowed->headers->get('Allow'));
        self::assertSame(['application/problem+json'], $notAllowed->headers->get('Content-Type'));
        self::assertSame(405, json_decode(implode('', iterator_to_array($notAllowed->body->chunks())), true)['status']);
        self::assertSame(ProtocolVersion::Http2, $notAllowed->protocolVersion);
    }

    public function testHandlerFailurePropagatesUnchanged(): void
    {
        $failure = new TypeError('Handler failure');
        $handler = $this->createMock(RequestHandler::class);
        $handler->expects(self::once())->method('handle')->willThrowException($failure);
        $routing = new RoutingRequestHandler(
            new SimpleRouter(new RouteCollection([new Route('route.5', Method::Get, '/', 'handler')])),
            $this->resolver($handler),
        );
        try {
            $routing->handle(new Request(Method::Get, new Uri('/')));
            self::fail('Expected handler failure.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function testRouterFailurePropagatesUnchanged(): void
    {
        $failure = new TypeError('Router failure');
        $router = $this->createMock(Router::class);
        $router->expects(self::once())->method('match')->willThrowException($failure);
        $router->expects(self::never())->method('allowedMethods');
        try {
            new RoutingRequestHandler($router, self::createStub(HandlerResolver::class))->handle(new Request(Method::Get, new Uri('/')));
            self::fail('Expected router failure.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function testResolutionFailurePropagatesUnchanged(): void
    {
        $failure = new HandlerResolutionException('Missing handler');
        $resolver = $this->createMock(HandlerResolver::class);
        $resolver->expects(self::once())->method('resolve')->with('missing')->willThrowException($failure);
        $routing = new RoutingRequestHandler(
            new SimpleRouter(new RouteCollection([new Route('route.6', Method::Get, '/', 'missing')])),
            $resolver,
        );
        try {
            $routing->handle(new Request(Method::Get, new Uri('/')));
            self::fail('Expected resolution failure.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }
    }

    private function resolver(RequestHandler $handler): HandlerResolver
    {
        $resolver = $this->createMock(HandlerResolver::class);
        $resolver->expects(self::once())->method('resolve')->with('handler')->willReturn($handler);

        return $resolver;
    }
}
