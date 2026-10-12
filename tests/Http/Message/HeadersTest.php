<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Message;

use ExtendsSoftware\ExaPHP\Http\Message\Exception\InvalidHeaderException;
use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HeadersTest extends TestCase
{
    public function testCaseInsensitiveLookupPreservesFirstSpellingAndSeparateValues(): void
    {
        $headers = new Headers(['Set-Cookie' => ['a=1', 'b=2'], 'set-cookie' => 'c=3', 'X-Empty' => '']);
        self::assertTrue($headers->has('SET-COOKIE'));
        self::assertSame(['a=1', 'b=2', 'c=3'], $headers->get('set-cookie'));
        self::assertSame(['Set-Cookie' => ['a=1', 'b=2', 'c=3'], 'X-Empty' => ['']], $headers->all());
        self::assertSame([], $headers->get('absent'));
    }

    public function testReplacementAppendAndRemovalAreImmutable(): void
    {
        $headers = new Headers(['X-Test' => 'first']);
        $added = $headers->withAdded('x-test', 'second');
        $replaced = $added->with('X-TEST', 'third', 'fourth');
        $removed = $replaced->without('x-test');
        self::assertSame(['first'], $headers->get('x-test'));
        self::assertSame(['X-Test' => ['first', 'second']], $added->all());
        self::assertSame(['X-TEST' => ['third', 'fourth']], $replaced->all());
        self::assertSame([], $removed->all());
        self::assertSame(['X-New' => ['value']], $removed->withAdded('X-New', 'value')->all());
        self::assertSame($headers->all(), $headers->without('absent')->all());
    }

    public function testTrimsOnlySurroundingSpacesAndTabsAndAcceptsOpaqueBytes(): void
    {
        $headers = new Headers(['X-Test' => " \talpha\tbeta\x80 \t", 123 => 'numeric name']);
        self::assertSame(["alpha\tbeta\x80"], $headers->get('x-test'));
        self::assertSame(['numeric name'], $headers->get('123'));
    }

    /**
     * @param array<array-key, mixed> $input
     */
    #[DataProvider('invalidHeaders')]
    public function testRejectsMalformedHeaders(array $input): void
    {
        $this->expectException(InvalidHeaderException::class);
        new Headers($input);
    }

    /**
     * @return iterable<array{array<array-key, mixed>}>
     */
    public static function invalidHeaders(): iterable
    {
        yield [['' => 'value']];
        yield [['bad name' => 'value']];
        yield [[':authority' => 'host']];
        yield [["X-Test\r\nInjected" => 'value']];
        yield [['X-Test' => "value\r\nInjected: true"]];
        yield [['X-Test' => "value\0"]];
        yield [['X-Test' => "value\x7F"]];
        yield [['X-Test' => "value\v"]];
        yield [['X-Test' => []]];
        yield [['X-Test' => [1 => 'value']]];
        yield [['X-Test' => [123]]];
        yield [['X-Test' => null]];
    }

    public function testInvalidLookupNameUsesComponentException(): void
    {
        $this->expectException(InvalidHeaderException::class);
        new Headers()->get('bad name');
    }
}
