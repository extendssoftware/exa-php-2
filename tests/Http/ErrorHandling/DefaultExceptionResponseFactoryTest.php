<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\ErrorHandling;

use ExtendsSoftware\ExaPHP\Http\ErrorHandling\DefaultExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\ProtocolVersion;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
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
            'Content-Type' => ['application/problem+json'],
            'Cache-Control' => ['no-store'],
        ], $response->headers->all());
        $this->assertSame(
            '{"type":"about:blank","title":"Internal Server Error","status":500}',
            implode('', iterator_to_array($response->body->chunks())),
        );
    }
}
