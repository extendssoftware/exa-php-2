<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Message;

use ExtendsSoftware\ExaPHP\Http\Message\Body\StringBody;
use ExtendsSoftware\ExaPHP\Http\Message\Exception\InvalidRequestException;
use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\ProtocolVersion;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testImmutableReplacementsPreserveOriginalAndBodyIdentity(): void
    {
        $body = new StringBody('payload');
        $request = new Request(Method::Post, new Uri('/original'), body: $body);
        $headers = new Headers(['X-Test' => 'value']);
        $updated = $request->withMethod(Method::Put)->withUri(new Uri('/updated'))->withHeaders($headers)
            ->withProtocolVersion(ProtocolVersion::Http2);
        $this->assertSame(Method::Post, $request->method);
        $this->assertSame('/original', $request->target());
        $this->assertSame(Method::Put, $updated->method);
        $this->assertSame('/updated', $updated->target());
        $this->assertSame($headers, $updated->headers);
        $this->assertSame($body, $updated->body);
        $this->assertSame(ProtocolVersion::Http2, $updated->protocolVersion);
        $replacement = new StringBody('new');
        $this->assertSame($replacement, $updated->withBody($replacement)->body);
        $this->assertSame($body, $updated->body);
    }

    #[DataProvider('targets')]
    public function testRequestTargets(Method $method, string $uri, string $target): void
    {
        $request = new Request($method, new Uri($uri));
        $this->assertSame($target, $request->target());
        $this->assertSame([], $request->headers->all());
    }

    /**
     * @return iterable<array{Method, string, string}>
     */
    public static function targets(): iterable
    {
        yield [Method::Get, '/articles/', '/articles/'];
        yield [Method::Get, '/a%2Fb?x=1&x=2', '/a%2Fb?x=1&x=2'];
        yield [Method::Get, 'https://host', '/'];
        yield [Method::Get, 'http://0/', '/'];
        yield [Method::Get, '?', '/?'];
        yield [Method::Options, '*', '*'];
        yield [Method::Connect, 'https://[::1]:443', '[::1]:443'];
    }

    #[DataProvider('invalidTargets')]
    public function testRejectsInvalidRequestTargets(Method $method, string $uri): void
    {
        $this->expectException(InvalidRequestException::class);
        new Request($method, new Uri($uri));
    }

    /**
     * @return iterable<array{Method, string}>
     */
    public static function invalidTargets(): iterable
    {
        yield [Method::Get, 'relative'];
        yield [Method::Get, 'https://'];
        yield [Method::Get, 'ftp://host/'];
        yield [Method::Get, '/#fragment'];
        yield [Method::Get, 'http://user@host/'];
        yield [Method::Get, 'http://host:65536/'];
        yield [Method::Get, '*'];
        yield [Method::Options, '*?query'];
        yield [Method::Connect, 'https://host'];
        yield [Method::Connect, 'https://host:443/path'];
    }

    public function testChangingMethodRevalidatesTarget(): void
    {
        $request = new Request(Method::Options, new Uri('*'));
        $this->expectException(InvalidRequestException::class);
        $request->withMethod(Method::Get);
    }
}
