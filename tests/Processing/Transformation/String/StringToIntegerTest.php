<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing\Transformation\String;

use ExtendsSoftware\ExaPHP\Processing\Transformation\String\StringToInteger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

final class StringToIntegerTest extends TestCase
{
    public function testViolationCodesRemainStable(): void
    {
        $this->assertSame('not_string', StringToInteger::CODE_NOT_STRING);
        $this->assertSame('invalid_integer_format', StringToInteger::CODE_INVALID_INTEGER_FORMAT);
        $this->assertSame('integer_overflow', StringToInteger::CODE_INTEGER_OVERFLOW);
    }

    #[DataProvider('inputs')]
    public function testInput(mixed $input, ?string $code, mixed $expected): void
    {
        $result = new StringToInteger()->transform($input);
        $this->assertSame($code === null, $result->isValid());
        if ($code !== null) {
            $this->assertCount(1, $result->violations());
            $this->assertSame($code, $result->violations()[0]->code());
            $this->assertSame([], $result->violations()[0]->path());
        } else {
            $this->assertSame([], $result->violations());
            $this->assertSame($expected, $result->value());
        }
    }

    /**
     * @return iterable<array{mixed, ?string, mixed}>
     */
    public static function inputs(): iterable
    {
        yield ['0', null, 0];
        yield ['-0', null, 0];
        yield ['+00042', null, 42];
        yield ['-0042', null, -42];
        yield [(string) PHP_INT_MAX, null, PHP_INT_MAX];
        yield [(string) PHP_INT_MIN, null, PHP_INT_MIN];
        yield [(string) PHP_INT_MAX . '0', StringToInteger::CODE_INTEGER_OVERFLOW, null];
        yield [(string) PHP_INT_MIN . '0', StringToInteger::CODE_INTEGER_OVERFLOW, null];
        yield ['', StringToInteger::CODE_INVALID_INTEGER_FORMAT, null];
        yield ['+', StringToInteger::CODE_INVALID_INTEGER_FORMAT, null];
        yield [' 1', StringToInteger::CODE_INVALID_INTEGER_FORMAT, null];
        yield ["1\n", StringToInteger::CODE_INVALID_INTEGER_FORMAT, null];
        yield ['1.0', StringToInteger::CODE_INVALID_INTEGER_FORMAT, null];
        yield ['1e2', StringToInteger::CODE_INVALID_INTEGER_FORMAT, null];
        yield ['0x10', StringToInteger::CODE_INVALID_INTEGER_FORMAT, null];
        yield ['--1', StringToInteger::CODE_INVALID_INTEGER_FORMAT, null];
        yield [1, StringToInteger::CODE_NOT_STRING, null];
        yield [null, StringToInteger::CODE_NOT_STRING, null];
    }
}
