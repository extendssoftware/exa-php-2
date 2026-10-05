<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http;

use ExtendsSoftware\ExaPHP\Http\Exception\InvalidUriException;
use ExtendsSoftware\ExaPHP\Http\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Uri\InvalidUriException as NativeInvalidUriException;

final class UriTest extends TestCase
{
    public function testPreservesOriginalComponentsAndSerialization(): void
    {
        $text = 'HTTPS://user:p%40ss@Example.COM:443/a/../b%2f/?x=1&x=2+#part%20one';
        $uri = new Uri($text);
        $this->assertSame($text, $uri->toString());
        $this->assertSame('HTTPS', $uri->scheme());
        $this->assertSame('user:p%40ss', $uri->userInfo());
        $this->assertSame('Example.COM', $uri->host());
        $this->assertSame(443, $uri->port());
        $this->assertSame('/a/../b%2f/', $uri->path());
        $this->assertSame('x=1&x=2+', $uri->query());
        $this->assertSame('part%20one', $uri->fragment());
    }

    public function testRelativeReferencesAndAbsentVersusEmptyComponents(): void
    {
        $uri = new Uri('/articles?');
        $this->assertNull($uri->scheme());
        $this->assertNull($uri->host());
        $this->assertNull($uri->port());
        $this->assertSame('', $uri->query());
        $this->assertNull($uri->fragment());
        $this->assertSame('', new Uri('#')->fragment());
        $this->assertNull(new Uri('')->query());
        $this->assertSame('relative/path', new Uri('relative/path')->path());
    }

    public function testIpv6DoesNotChangeOriginalRepresentation(): void
    {
        $text = 'http://[::1]:8080/path';
        $uri = new Uri($text);
        $this->assertSame('[::1]', $uri->host());
        $this->assertSame(8080, $uri->port());
        $this->assertSame($text, $uri->toString());
    }

    #[DataProvider('invalidUris')]
    public function testTranslatesInvalidUriFailures(string $input): void
    {
        try {
            new Uri($input);
            $this->fail('Expected invalid URI.');
        } catch (InvalidUriException $exception) {
            $this->assertInstanceOf(NativeInvalidUriException::class, $exception->getPrevious());
        }
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidUris(): iterable
    {
        yield ['a b'];
        yield ['/bad%zz'];
        yield ["/bad\r\n"];
        yield ['http://[not-ip]/'];
        yield ['http://host:abc/'];
    }
}
