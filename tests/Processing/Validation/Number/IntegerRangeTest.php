<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing\Validation\Number;

use ExtendsSoftware\ExaPHP\Processing\Validation\Exception\InvalidIntegerRangeException;
use ExtendsSoftware\ExaPHP\Processing\Validation\Number\IntegerRange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IntegerRangeTest extends TestCase
{
    public function testViolationCodesRemainStable(): void
    {
        self::assertSame('not_integer', IntegerRange::CODE_NOT_INTEGER);
        self::assertSame('integer_out_of_range', IntegerRange::CODE_INTEGER_OUT_OF_RANGE);
    }

    #[DataProvider('inputs')]
    public function testInput(mixed $input, ?string $code): void
    {
        $result = new IntegerRange(-2, 2)->validate($input);
        self::assertSame($code === null, $result->isValid());
        if ($code !== null) {
            self::assertCount(1, $result->violations());
            self::assertSame($code, $result->violations()[0]->code());
            self::assertSame([], $result->violations()[0]->path());
        } else {
            self::assertSame([], $result->violations());

        }
    }

    public function testRejectsReversedBounds(): void
    {
        $this->expectException(InvalidIntegerRangeException::class);
        new IntegerRange(2, 1);
    }

    public function testEqualBoundsAndFailureParameters(): void
    {
        $validator = new IntegerRange(1, 1);
        self::assertTrue($validator->validate(1)->isValid());
        self::assertSame(['minimum' => 1, 'maximum' => 1], $validator->validate(0)->violations()[0]->parameters());
    }

    /**
     * @return iterable<array{mixed, ?string}>
     */
    public static function inputs(): iterable
    {
        yield [-2, null];
        yield [2, null];
        yield [0, null];
        yield [-3, IntegerRange::CODE_INTEGER_OUT_OF_RANGE];
        yield [3, IntegerRange::CODE_INTEGER_OUT_OF_RANGE];
        yield ['1', IntegerRange::CODE_NOT_INTEGER];
        yield [1.0, IntegerRange::CODE_NOT_INTEGER];
        yield [null, IntegerRange::CODE_NOT_INTEGER];
    }
}
