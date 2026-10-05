<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing\Validation\String;

use ExtendsSoftware\ExaPHP\Processing\Exception\InvalidStringLengthException;
use ExtendsSoftware\ExaPHP\Processing\Validation\String\StringLength;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StringLengthTest extends TestCase
{
    public function testViolationCodesRemainStable(): void
    {
        $this->assertSame('not_string', StringLength::CODE_NOT_STRING);
        $this->assertSame('invalid_utf8', StringLength::CODE_INVALID_UTF8);
        $this->assertSame('string_length_out_of_range', StringLength::CODE_STRING_LENGTH_OUT_OF_RANGE);
    }

    #[DataProvider('inputs')]
    public function testCountsCodePoints(mixed $input, ?string $code): void
    {
        $result = new StringLength(1, 2)->validate($input);
        $this->assertSame($code === null, $result->isValid());
        if ($code !== null) {
            $this->assertSame($code, $result->violations()[0]->code());
        }
    }

    /**
     * @return iterable<array{mixed, ?string}>
     */
    public static function inputs(): iterable
    {
        yield ['é', null];
        yield ['😀', null];
        yield ["e\u{0301}", null];
        yield ["a\n", null];
        yield ['', StringLength::CODE_STRING_LENGTH_OUT_OF_RANGE];
        yield ['abc', StringLength::CODE_STRING_LENGTH_OUT_OF_RANGE];
        yield ["\xFF", StringLength::CODE_INVALID_UTF8];
        yield [null, StringLength::CODE_NOT_STRING];
        yield [1, StringLength::CODE_NOT_STRING];
    }

    public function testParametersAndZeroBounds(): void
    {
        $this->assertTrue(new StringLength(0, 0)->validate('')->isValid());
        $violation = new StringLength(1, 1)->validate("e\u{0301}")->violations()[0];
        $this->assertSame(['minimum' => 1, 'maximum' => 1, 'length' => 2], $violation->parameters());
    }

    #[DataProvider('bounds')]
    public function testRejectsInvalidBounds(int $minimum, int $maximum): void
    {
        $this->expectException(InvalidStringLengthException::class);
        new StringLength($minimum, $maximum);
    }

    /**
     * @return iterable<array{int, int}>
     */
    public static function bounds(): iterable
    {
        yield [-1, 2];
        yield [2, 1];
    }
}
