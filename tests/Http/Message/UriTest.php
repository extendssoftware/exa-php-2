<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Message;

use ExtendsSoftware\ExaPHP\Http\Message\Exception\InvalidUriException;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Uri\InvalidUriException as NativeInvalidUriException;

final class UriTest extends TestCase
{
    public function testPreservesOriginalComponentsAndSerialization(): void
    {
        $text = 'HTTPS://user:p%40ss@Example.COM:443/a/../b%2f/?x=1&x=2+#part%20one';
        $uri = new Uri($text);
        self::assertSame($text, $uri->toString());
        self::assertSame('HTTPS', $uri->scheme());
        self::assertSame('user:p%40ss', $uri->userInfo());
        self::assertSame('Example.COM', $uri->host());
        self::assertSame(443, $uri->port());
        self::assertSame('/a/../b%2f/', $uri->path());
        self::assertSame('x=1&x=2+', $uri->query());
        self::assertSame('part%20one', $uri->fragment());
    }

    public function testRelativeReferencesAndAbsentVersusEmptyComponents(): void
    {
        $uri = new Uri('/articles?');
        self::assertNull($uri->scheme());
        self::assertNull($uri->host());
        self::assertNull($uri->port());
        self::assertSame('', $uri->query());
        self::assertNull($uri->fragment());
        self::assertSame('', new Uri('#')->fragment());
        self::assertNull(new Uri('')->query());
        self::assertSame('relative/path', new Uri('relative/path')->path());
    }

    public function testIpv6DoesNotChangeOriginalRepresentation(): void
    {
        $text = 'http://[::1]:8080/path';
        $uri = new Uri($text);
        self::assertSame('[::1]', $uri->host());
        self::assertSame(8080, $uri->port());
        self::assertSame($text, $uri->toString());
    }

    #[DataProvider('invalidUris')]
    public function testTranslatesInvalidUriFailures(string $input): void
    {
        try {
            new Uri($input);
            self::fail('Expected invalid URI.');
        } catch (InvalidUriException $exception) {
            self::assertInstanceOf(NativeInvalidUriException::class, $exception->getPrevious());
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
