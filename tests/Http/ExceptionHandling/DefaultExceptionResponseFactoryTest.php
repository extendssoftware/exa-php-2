<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\ExceptionHandling;

use ExtendsSoftware\ExaPHP\Http\ExceptionHandling\DefaultExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Method;
use ExtendsSoftware\ExaPHP\Http\ProtocolVersion;
use ExtendsSoftware\ExaPHP\Http\Request;
use ExtendsSoftware\ExaPHP\Http\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Uri;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function implode;
use function iterator_to_array;

final class DefaultExceptionResponseFactoryTest extends TestCase
{
    public function testCreatesGeneric500WithoutExposingExceptionDetailsOrCode(): void
    {
        $request = new Request(Method::Get, new Uri('/'), protocolVersion: ProtocolVersion::Http2);
        $exception = new RuntimeException('Secret credentials', 404, new RuntimeException('Private cause'));
        $response = new DefaultExceptionResponseFactory()->create($exception, $request);
        $this->assertSame(StatusCode::InternalServerError, $response->statusCode);
        $this->assertSame(ProtocolVersion::Http2, $response->protocolVersion);
        $this->assertSame([
            'Content-Type' => ['text/plain; charset=utf-8'],
            'Cache-Control' => ['no-store'],
        ], $response->headers->all());
        $this->assertSame('Internal Server Error', implode('', iterator_to_array($response->body->chunks())));
    }
}
