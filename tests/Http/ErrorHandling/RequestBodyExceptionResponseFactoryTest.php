<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\ErrorHandling;

use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\MalformedRequestBodyException;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\RequestBodyTooLargeException;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\UnsupportedRequestMediaTypeException;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\RequestBodyExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\ProtocolVersion;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use TypeError;

use function implode;
use function iterator_to_array;
use function json_decode;

final class RequestBodyExceptionResponseFactoryTest extends TestCase
{
    #[DataProvider('failures')]
    public function testMapsKnownFailuresWithoutExposingDetails(Throwable $failure, StatusCode $status): void
    {
        $fallback = $this->createMock(ExceptionResponseFactory::class);
        $fallback->expects(self::never())->method('create');
        $request = new Request(Method::Post, new Uri('/'), protocolVersion: ProtocolVersion::Http2);
        $response = new RequestBodyExceptionResponseFactory($fallback)->create($failure, $request);
        self::assertSame($status, $response->statusCode);
        self::assertSame(ProtocolVersion::Http2, $response->protocolVersion);
        self::assertSame(['application/problem+json'], $response->headers->get('Content-Type'));
        $data = json_decode(implode('', iterator_to_array($response->body->chunks())), true);
        self::assertSame('about:blank', $data['type']);
        self::assertSame($status->value, $data['status']);
        self::assertSame(['no-store'], $response->headers->get('Cache-Control'));
        self::assertStringNotContainsString('secret', implode('', iterator_to_array($response->body->chunks())));
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
        $fallback->expects(self::once())->method('create')->with($failure, $request)->willReturn($response);
        self::assertSame($response, new RequestBodyExceptionResponseFactory($fallback)->create($failure, $request));
    }
}
