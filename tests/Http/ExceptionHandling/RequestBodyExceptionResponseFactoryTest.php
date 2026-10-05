<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\ExceptionHandling;

use ExtendsSoftware\ExaPHP\Http\Exception\MalformedRequestBodyException;
use ExtendsSoftware\ExaPHP\Http\Exception\RequestBodyTooLargeException;
use ExtendsSoftware\ExaPHP\Http\Exception\UnsupportedRequestMediaTypeException;
use ExtendsSoftware\ExaPHP\Http\ExceptionHandling\ExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\ExceptionHandling\RequestBodyExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Method;
use ExtendsSoftware\ExaPHP\Http\ProtocolVersion;
use ExtendsSoftware\ExaPHP\Http\Request;
use ExtendsSoftware\ExaPHP\Http\Response;
use ExtendsSoftware\ExaPHP\Http\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use TypeError;

use function implode;
use function iterator_to_array;

final class RequestBodyExceptionResponseFactoryTest extends TestCase
{
    #[DataProvider('failures')]
    public function testMapsKnownFailuresWithoutExposingDetails(Throwable $failure, StatusCode $status): void
    {
        $fallback = $this->createMock(ExceptionResponseFactory::class);
        $fallback->expects($this->never())->method('create');
        $request = new Request(Method::Post, new Uri('/'), protocolVersion: ProtocolVersion::Http2);
        $response = new RequestBodyExceptionResponseFactory($fallback)->create($failure, $request);
        $this->assertSame($status, $response->statusCode);
        $this->assertSame(ProtocolVersion::Http2, $response->protocolVersion);
        $this->assertSame(['no-store'], $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('secret', implode('', iterator_to_array($response->body->chunks())));
    }

    /** @return iterable<array{Throwable, StatusCode}> */
    public static function failures(): iterable
    {
        yield [new MalformedRequestBodyException('secret'), StatusCode::BadRequest];
        yield [new RequestBodyTooLargeException('secret'), StatusCode::ContentTooLarge];
        yield [new UnsupportedRequestMediaTypeException('secret'), StatusCode::UnsupportedMediaType];
    }

    public function testDelegatesUnrelatedFailuresUnchanged(): void
    {
        $failure = new TypeError('Failure');
        $request = new Request(Method::Post, new Uri('/'));
        $response = new Response();
        $fallback = $this->createMock(ExceptionResponseFactory::class);
        $fallback->expects($this->once())->method('create')->with($failure, $request)->willReturn($response);
        $this->assertSame($response, new RequestBodyExceptionResponseFactory($fallback)->create($failure, $request));
    }
}
