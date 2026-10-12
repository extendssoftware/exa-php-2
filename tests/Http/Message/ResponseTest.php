<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Message;

use ExtendsSoftware\ExaPHP\Http\Message\Body\StringBody;
use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\Message\ProtocolVersion;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testDefaultsAndImmutableUpdates(): void
    {
        $original = new Response();
        $body = new StringBody('created');
        $headers = new Headers(['Content-Type' => 'text/plain']);
        $updated = $original->withStatusCode(StatusCode::Created)->withHeaders($headers)->withBody($body)
            ->withProtocolVersion(ProtocolVersion::Http2);
        self::assertSame(StatusCode::Ok, $original->statusCode);
        self::assertSame([], $original->headers->all());
        self::assertSame(0, $original->body->size());
        self::assertSame(StatusCode::Created, $updated->statusCode);
        self::assertSame(201, $updated->statusCode->value);
        self::assertSame($headers, $updated->headers);
        self::assertSame($body, $updated->body);
        self::assertSame(ProtocolVersion::Http2, $updated->protocolVersion);
        self::assertSame($body, $updated->withStatusCode(StatusCode::Accepted)->body);
    }

    public function testEverySupportedStatusCanBeUsedInAResponse(): void
    {
        foreach (StatusCode::cases() as $status) {
            self::assertSame($status, new Response($status)->statusCode);
        }
    }
}
