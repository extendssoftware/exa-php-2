<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Middleware;

use ExtendsSoftware\ExaPHP\Http\ErrorHandling\DefaultExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use ExtendsSoftware\ExaPHP\Http\Middleware\ExceptionHandlingMiddleware;
use ExtendsSoftware\ExaPHP\Http\Middleware\Middleware;
use ExtendsSoftware\ExaPHP\Http\Middleware\MiddlewarePipeline;
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
        $handler->expects(self::once())->method('handle')->with(self::identicalTo($request))->willReturn($response);
        $factory = $this->createMock(ExceptionResponseFactory::class);
        $factory->expects(self::never())->method('create');
        self::assertSame($response, new ExceptionHandlingMiddleware($factory)->process($request, $handler));
    }

    #[DataProvider('failures')]
    public function testPassesOriginalFailureAndBoundaryRequestToFactory(Throwable $failure): void
    {
        $request = new Request(Method::Get, new Uri('/'));
        $response = new Response(StatusCode::UnprocessableContent);
        $handler = $this->createMock(RequestHandler::class);
        $handler->expects(self::once())->method('handle')->willThrowException($failure);
        $factory = $this->createMock(ExceptionResponseFactory::class);
        $factory->expects(self::once())->method('create')
            ->with(self::identicalTo($failure), self::identicalTo($request))->willReturn($response);
        self::assertSame($response, new ExceptionHandlingMiddleware($factory)->process($request, $handler));
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
        $handler = self::createStub(RequestHandler::class);
        $handler->method('handle')->willThrowException(new RuntimeException('Original failure'));
        $factory = $this->createMock(ExceptionResponseFactory::class);
        $factory->expects(self::once())->method('create')->willThrowException($failure);
        try {
            new ExceptionHandlingMiddleware($factory)->process(new Request(Method::Get, new Uri('/')), $handler);
            self::fail('Expected factory failure.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function testOutermostBoundaryCatchesDownstreamMiddlewareFailure(): void
    {
        $failure = new RuntimeException('Middleware failure');
        $middleware = $this->createMock(Middleware::class);
        $middleware->expects(self::once())->method('process')->willThrowException($failure);
        $handler = $this->createMock(RequestHandler::class);
        $handler->expects(self::never())->method('handle');
        $pipeline = new MiddlewarePipeline($handler, [
            new ExceptionHandlingMiddleware(new DefaultExceptionResponseFactory()),
            $middleware,
        ]);
        self::assertSame(
            StatusCode::InternalServerError,
            $pipeline->handle(new Request(Method::Get, new Uri('/')))->statusCode,
        );
    }
}
