<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Middleware;

use ExtendsSoftware\ExaPHP\Http\ErrorHandling\DefaultExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Middleware\ExceptionHandlingMiddleware;
use ExtendsSoftware\ExaPHP\Http\Middleware\Middleware;
use ExtendsSoftware\ExaPHP\Http\Middleware\MiddlewarePipeline;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use TypeError;

final class ExceptionHandlingMiddlewareTest extends TestCase
{
    public function testSuccessfulResponsePassesThroughWithoutCallingFactory(): void
    {
        $request = new Request(Method::Get, new Uri('/'));
        $response = new Response();
        $handler = $this->createMock(RequestHandler::class);
        $handler->expects($this->once())->method('handle')->with($this->identicalTo($request))->willReturn($response);
        $factory = $this->createMock(ExceptionResponseFactory::class);
        $factory->expects($this->never())->method('create');
        $this->assertSame($response, new ExceptionHandlingMiddleware($factory)->process($request, $handler));
    }

    #[DataProvider('failures')]
    public function testPassesOriginalFailureAndBoundaryRequestToFactory(Throwable $failure): void
    {
        $request = new Request(Method::Get, new Uri('/'));
        $response = new Response(StatusCode::UnprocessableContent);
        $handler = $this->createMock(RequestHandler::class);
        $handler->expects($this->once())->method('handle')->willThrowException($failure);
        $factory = $this->createMock(ExceptionResponseFactory::class);
        $factory->expects($this->once())->method('create')
            ->with($this->identicalTo($failure), $this->identicalTo($request))->willReturn($response);
        $this->assertSame($response, new ExceptionHandlingMiddleware($factory)->process($request, $handler));
    }

    /** @return iterable<array{Throwable}> */
    public static function failures(): iterable
    {
        yield [new RuntimeException('Application failure')];
        yield [new TypeError('Engine error')];
    }

    public function testFactoryFailurePropagatesUnchangedWithoutRetry(): void
    {
        $failure = new TypeError('Factory failure');
        $handler = $this->createStub(RequestHandler::class);
        $handler->method('handle')->willThrowException(new RuntimeException('Original failure'));
        $factory = $this->createMock(ExceptionResponseFactory::class);
        $factory->expects($this->once())->method('create')->willThrowException($failure);
        try {
            new ExceptionHandlingMiddleware($factory)->process(new Request(Method::Get, new Uri('/')), $handler);
            $this->fail('Expected factory failure.');
        } catch (Throwable $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    public function testOutermostBoundaryCatchesDownstreamMiddlewareFailure(): void
    {
        $failure = new RuntimeException('Middleware failure');
        $middleware = $this->createMock(Middleware::class);
        $middleware->expects($this->once())->method('process')->willThrowException($failure);
        $handler = $this->createMock(RequestHandler::class);
        $handler->expects($this->never())->method('handle');
        $pipeline = new MiddlewarePipeline($handler, [
            new ExceptionHandlingMiddleware(new DefaultExceptionResponseFactory()),
            $middleware,
        ]);
        $this->assertSame(
            StatusCode::InternalServerError,
            $pipeline->handle(new Request(Method::Get, new Uri('/')))->statusCode,
        );
    }
}
