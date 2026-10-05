<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http;

use ExtendsSoftware\ExaPHP\Http\Body\StringBody;
use ExtendsSoftware\ExaPHP\Http\Headers;
use ExtendsSoftware\ExaPHP\Http\ProtocolVersion;
use ExtendsSoftware\ExaPHP\Http\Response;
use ExtendsSoftware\ExaPHP\Http\StatusCode;
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
        $this->assertSame(StatusCode::Ok, $original->statusCode);
        $this->assertSame([], $original->headers->all());
        $this->assertSame(0, $original->body->size());
        $this->assertSame(StatusCode::Created, $updated->statusCode);
        $this->assertSame(201, $updated->statusCode->value);
        $this->assertSame($headers, $updated->headers);
        $this->assertSame($body, $updated->body);
        $this->assertSame(ProtocolVersion::Http2, $updated->protocolVersion);
        $this->assertSame($body, $updated->withStatusCode(StatusCode::Accepted)->body);
    }

    public function testEverySupportedStatusCanBeUsedInAResponse(): void
    {
        foreach (StatusCode::cases() as $status) {
            $this->assertSame($status, new Response($status)->statusCode);
        }
    }
}
