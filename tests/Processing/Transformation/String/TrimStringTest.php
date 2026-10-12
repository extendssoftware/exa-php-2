<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing\Transformation\String;

use ExtendsSoftware\ExaPHP\Processing\Transformation\String\TrimString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TrimStringTest extends TestCase
{
    public function testViolationCodesRemainStable(): void
    {
        self::assertSame('not_string', TrimString::CODE_NOT_STRING);
    }

    #[DataProvider('inputs')]
    public function testInput(mixed $input, ?string $code, mixed $expected): void
    {
        $result = new TrimString()->transform($input);
        self::assertSame($code === null, $result->isValid());
        if ($code !== null) {
            self::assertCount(1, $result->violations());
            self::assertSame($code, $result->violations()[0]->code());
            self::assertSame([], $result->violations()[0]->path());
        } else {
            self::assertSame([], $result->violations());
            self::assertSame($expected, $result->value());
        }
    }

    /**
     * @return iterable<array{mixed, ?string, mixed}>
     */
    public static function inputs(): iterable
    {
        yield [' text ', null, 'text'];
        yield ["\t\ntext\r\0\v", null, 'text'];
        yield ['', null, ''];
        yield ['a b', null, 'a b'];
        yield ["\u{00A0}", null, "\u{00A0}"];
        yield [null, TrimString::CODE_NOT_STRING, null];
        yield [42, TrimString::CODE_NOT_STRING, null];
    }
}
