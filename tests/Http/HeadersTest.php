<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http;

use ExtendsSoftware\ExaPHP\Http\Exception\InvalidHeaderException;
use ExtendsSoftware\ExaPHP\Http\Headers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HeadersTest extends TestCase
{
    public function testCaseInsensitiveLookupPreservesFirstSpellingAndSeparateValues(): void
    {
        $headers = new Headers(['Set-Cookie' => ['a=1', 'b=2'], 'set-cookie' => 'c=3', 'X-Empty' => '']);
        $this->assertTrue($headers->has('SET-COOKIE'));
        $this->assertSame(['a=1', 'b=2', 'c=3'], $headers->get('set-cookie'));
        $this->assertSame(['Set-Cookie' => ['a=1', 'b=2', 'c=3'], 'X-Empty' => ['']], $headers->all());
        $this->assertSame([], $headers->get('absent'));
    }

    public function testReplacementAppendAndRemovalAreImmutable(): void
    {
        $headers = new Headers(['X-Test' => 'first']);
        $added = $headers->withAdded('x-test', 'second');
        $replaced = $added->with('X-TEST', 'third', 'fourth');
        $removed = $replaced->without('x-test');
        $this->assertSame(['first'], $headers->get('x-test'));
        $this->assertSame(['X-Test' => ['first', 'second']], $added->all());
        $this->assertSame(['X-TEST' => ['third', 'fourth']], $replaced->all());
        $this->assertSame([], $removed->all());
        $this->assertSame(['X-New' => ['value']], $removed->withAdded('X-New', 'value')->all());
        $this->assertSame($headers->all(), $headers->without('absent')->all());
    }

    public function testTrimsOnlySurroundingSpacesAndTabsAndAcceptsOpaqueBytes(): void
    {
        $headers = new Headers(['X-Test' => " \talpha\tbeta\x80 \t", 123 => 'numeric name']);
        $this->assertSame(["alpha\tbeta\x80"], $headers->get('x-test'));
        $this->assertSame(['numeric name'], $headers->get('123'));
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
