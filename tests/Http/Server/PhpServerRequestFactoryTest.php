<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Server;

use ExtendsSoftware\ExaPHP\Http\Message\Body\StringBody;
use ExtendsSoftware\ExaPHP\Http\HttpException;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\ProtocolVersion;
use ExtendsSoftware\ExaPHP\Http\Server\PhpServerRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhpServerRequestFactoryTest extends TestCase
{
    public function testPreservesTargetHeadersAndBodyWithoutTrustingForwardedHeaders(): void
    {
        $body = new StringBody('input');
        $request = new PhpServerRequestFactory()->fromServer([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/a%2Fb?x=1&x=2',
            'SERVER_PROTOCOL' => 'HTTP/2.0',
            'HTTP_HOST' => 'example.test',
            'HTTP_X_FORWARDED_HOST' => 'untrusted.test',
            'HTTP_AUTHORIZATION' => 'Bearer token',
            'HTTP_X_EMPTY' => '',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => '5',
        ], $body);
        $this->assertSame(Method::Post, $request->method);
        $this->assertSame(ProtocolVersion::Http2, $request->protocolVersion);
        $this->assertSame('/a%2Fb?x=1&x=2', $request->target());
        $this->assertSame('example.test', $request->uri->host());
        $this->assertSame('http', $request->uri->scheme());
        $this->assertSame(['example.test'], $request->headers->get('Host'));
        $this->assertSame(['Bearer token'], $request->headers->get('Authorization'));
        $this->assertSame([''], $request->headers->get('X-Empty'));
        $this->assertSame(['application/json'], $request->headers->get('Content-Type'));
        $this->assertSame(['5'], $request->headers->get('Content-Length'));
        $this->assertSame($body, $request->body);
    }

    public function testPreservesDoubleSlashPathsAndUsesDirectHttpsFlag(): void
    {
        $request = new PhpServerRequestFactory()->fromServer([
            'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '//a/%2F?x', 'SERVER_PROTOCOL' => 'HTTP/1.0',
            'HTTP_HOST' => '[::1]:8443', 'HTTPS' => 'on', 'HTTP_X_FORWARDED_PROTO' => 'http',
        ], new StringBody());
        $this->assertSame('https://[::1]:8443//a/%2F?x', $request->uri->toString());
        $this->assertSame('//a/%2F?x', $request->target());
    }

    #[DataProvider('targets')]
    public function testSupportsRequestTargetForms(string $method, string $target, string $expected): void
    {
        $request = new PhpServerRequestFactory()->fromServer([
            'REQUEST_METHOD' => $method, 'REQUEST_URI' => $target, 'SERVER_PROTOCOL' => 'HTTP/1.1',
        ], new StringBody());
        $this->assertSame($expected, $request->target());
    }

    /** @return iterable<array{string, string, string}> */
    public static function targets(): iterable
    {
        yield ['OPTIONS', '*', '*'];
        yield ['CONNECT', 'example.test:443', 'example.test:443'];
        yield ['GET', 'https://example.test/a?q', '/a?q'];
    }

    /** @param array<string, mixed> $overrides */
    #[DataProvider('invalidServer')]
    public function testRejectsInvalidServerMetadata(array $overrides): void
    {
        $this->expectException(HttpException::class);
        new PhpServerRequestFactory()->fromServer($overrides + [
            'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'SERVER_PROTOCOL' => 'HTTP/1.1',
        ], new StringBody());
    }

    /** @return iterable<array{array<string, mixed>}> */
    public static function invalidServer(): iterable
    {
        yield [['REQUEST_METHOD' => null]];
        yield [['REQUEST_METHOD' => 'CUSTOM']];
        yield [['SERVER_PROTOCOL' => 'HTTP/9']];
        yield [['REQUEST_URI' => '']];
        yield [['REQUEST_URI' => '//other/path']];
        yield [['REQUEST_URI' => '?query']];
        yield [['REQUEST_URI' => '/path#fragment']];
        yield [['HTTP_HOST' => 'host/path']];
        yield [['HTTP_HOST' => 'user@host']];
        yield [['HTTP_HOST' => 'host?query']];
        yield [['HTTP_TEST' => []]];
        yield [['CONTENT_LENGTH' => 12]];
        yield [['HTTP_TEST' => "bad\r\nheader"]];
    }
}
