<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Middleware;

use ExtendsSoftware\ExaPHP\Http\Message\Exception\InvalidHeaderException;
use ExtendsSoftware\ExaPHP\Http\Middleware\Exception\InvalidMiddlewareException;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Middleware\Middleware;
use ExtendsSoftware\ExaPHP\Http\Middleware\MiddlewarePipeline;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;
use TypeError;

final class MiddlewarePipelineTest extends TestCase
{
    public function testEmptyPipelineDelegatesUnchanged(): void
    {
        $request = new Request(Method::Get, new Uri('/'));
        $response = new Response();
        $handler = $this->createMock(RequestHandler::class);
        $handler->expects($this->once())->method('handle')->with($this->identicalTo($request))->willReturn($response);
        $this->assertSame($response, new MiddlewarePipeline($handler)->handle($request));
    }

    public function testRequestAndResponseOrderAndReplacement(): void
    {
        $calls = [];
        $original = new Request(Method::Get, new Uri('/original'));
        $replacement = $original->withUri(new Uri('/replacement'));
        $response = new Response();
        $handler = $this->createMock(RequestHandler::class);
        $handler->expects($this->once())->method('handle')->with($this->identicalTo($replacement))
            ->willReturnCallback(static function () use (&$calls, $response): Response {
                $calls[] = 'handler';

                return $response;
            });
        $first = $this->createMock(Middleware::class);
        $first->expects($this->once())->method('process')->with($this->identicalTo($original), $this->anything())
            ->willReturnCallback(static function (Request $request, RequestHandler $next) use (&$calls, $replacement) {
                $calls[] = 'first-in';
                $result = $next->handle($replacement);
                $calls[] = 'first-out';

                return $result->withHeaders($result->headers->withAdded('X-Order', 'first'));
            });
        $second = $this->createMock(Middleware::class);
        $second->expects($this->once())->method('process')->with($this->identicalTo($replacement), $this->anything())
            ->willReturnCallback(static function (Request $request, RequestHandler $next) use (&$calls) {
                $calls[] = 'second-in';
                $result = $next->handle($request);
                $calls[] = 'second-out';

                return $result->withHeaders($result->headers->withAdded('X-Order', 'second'));
            });
        $result = new MiddlewarePipeline($handler, [$first, $second])->handle($original);
        $this->assertSame(['first-in', 'second-in', 'handler', 'second-out', 'first-out'], $calls);
        $this->assertSame(['second', 'first'], $result->headers->get('X-Order'));
        $this->assertSame([], $response->headers->all());
        $this->assertSame('/original', $original->target());
    }

    public function testEarlyResponseSkipsRemainingChain(): void
    {
        $response = new Response(StatusCode::Forbidden);
        $early = $this->createMock(Middleware::class);
        $early->expects($this->once())->method('process')->willReturn($response);
        $later = $this->createMock(Middleware::class);
        $later->expects($this->never())->method('process');
        $handler = $this->createMock(RequestHandler::class);
        $handler->expects($this->never())->method('handle');
        $this->assertSame(
            $response,
            new MiddlewarePipeline($handler, [$early, $later])->handle(new Request(Method::Get, new Uri('/'))),
        );
    }

    public function testRepeatedRegistrationsAndRequestsStartFresh(): void
    {
        $handler = $this->createMock(RequestHandler::class);
        $handler->expects($this->exactly(2))->method('handle')->willReturn(new Response());
        $middleware = $this->createMock(Middleware::class);
        $middleware->expects($this->exactly(4))->method('process')->willReturnCallback(
            static fn(Request $request, RequestHandler $next): Response => $next->handle($request),
        );
        $pipeline = new MiddlewarePipeline($handler, [$middleware, $middleware]);
        $pipeline->handle(new Request(Method::Get, new Uri('/one')));
        $pipeline->handle(new Request(Method::Get, new Uri('/two')));
    }

    public function testRepeatedDelegationDoesNotConsumeRemainingChain(): void
    {
        $handler = $this->createMock(RequestHandler::class);
        $handler->expects($this->exactly(2))->method('handle')->willReturn(new Response());
        $twice = $this->createMock(Middleware::class);
        $twice->expects($this->once())->method('process')->willReturnCallback(
            static function (Request $request, RequestHandler $next): Response {
                $next->handle($request);

                return $next->handle($request);
            },
        );
        $later = $this->createMock(Middleware::class);
        $later->expects($this->exactly(2))->method('process')->willReturnCallback(
            static fn(Request $request, RequestHandler $next): Response => $next->handle($request),
        );
        new MiddlewarePipeline($handler, [$twice, $later])->handle(new Request(Method::Get, new Uri('/')));
    }

    /**
     * @param array<array-key, mixed> $middleware
     */
    #[DataProvider('invalidRegistrations')]
    public function testRejectsInvalidRegistrations(array $middleware): void
    {
        $this->expectException(InvalidMiddlewareException::class);
        new MiddlewarePipeline($this->createStub(RequestHandler::class), $middleware);
    }

    /**
     * @return iterable<array{array<array-key, mixed>}>
     */
    public static function invalidRegistrations(): iterable
    {
        yield [[null]];
        yield [[new stdClass()]];
        yield [['named' => new stdClass()]];
    }

    #[DataProvider('failures')]
    public function testPropagatesFailuresUnchanged(Throwable $failure): void
    {
        $handler = $this->createMock(RequestHandler::class);
        $handler->expects($this->once())->method('handle')->willThrowException($failure);
        $middleware = $this->createMock(Middleware::class);
        $middleware->expects($this->once())->method('process')->willReturnCallback(
            static fn(Request $request, RequestHandler $next): Response => $next->handle($request),
        );
        try {
            new MiddlewarePipeline($handler, [$middleware])->handle(new Request(Method::Get, new Uri('/')));
            $this->fail('Expected failure.');
        } catch (Throwable $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    /**
     * @return iterable<array{Throwable}>
     */
    public static function failures(): iterable
    {
        yield [new InvalidHeaderException('Invalid header')];
        yield [new TypeError('Unexpected error')];
    }

    public function testMiddlewareCanRecoverFromHandlerFailure(): void
    {
        $handler = $this->createStub(RequestHandler::class);
        $handler->method('handle')->willThrowException(new InvalidHeaderException('Invalid header'));
        $middleware = $this->createMock(Middleware::class);
        $middleware->expects($this->once())->method('process')->willReturnCallback(
            static function (Request $request, RequestHandler $next): Response {
                try {
                    return $next->handle($request);
                } catch (InvalidHeaderException) {
                    return new Response(StatusCode::BadRequest);
                }
            },
        );
        $response = new MiddlewarePipeline($handler, [$middleware])->handle(new Request(Method::Get, new Uri('/')));
        $this->assertSame(StatusCode::BadRequest, $response->statusCode);
    }
}
