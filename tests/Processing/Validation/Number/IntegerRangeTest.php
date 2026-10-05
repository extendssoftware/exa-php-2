<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing\Validation\Number;

use ExtendsSoftware\ExaPHP\Processing\Validation\Number\IntegerRange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use ExtendsSoftware\ExaPHP\Processing\Validation\Exception\InvalidIntegerRangeException;

final class IntegerRangeTest extends TestCase
{
    public function testViolationCodesRemainStable(): void
    {
        $this->assertSame('not_integer', IntegerRange::CODE_NOT_INTEGER);
        $this->assertSame('integer_out_of_range', IntegerRange::CODE_INTEGER_OUT_OF_RANGE);
    }

    #[DataProvider('inputs')]
    public function testInput(mixed $input, ?string $code): void
    {
        $result = new IntegerRange(-2, 2)->validate($input);
        $this->assertSame($code === null, $result->isValid());
        if ($code !== null) {
            $this->assertCount(1, $result->violations());
            $this->assertSame($code, $result->violations()[0]->code());
            $this->assertSame([], $result->violations()[0]->path());
        } else {
            $this->assertSame([], $result->violations());

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
        $this->assertTrue($validator->validate(1)->isValid());
        $this->assertSame(['minimum' => 1, 'maximum' => 1], $validator->validate(0)->violations()[0]->parameters());
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
