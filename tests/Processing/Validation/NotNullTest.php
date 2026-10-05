<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing\Validation;

use ExtendsSoftware\ExaPHP\Processing\Validation\NotNull;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NotNullTest extends TestCase
{
    public function testViolationCodesRemainStable(): void
    {
        $this->assertSame('null_value', NotNull::CODE_NULL_VALUE);
    }

    #[DataProvider('inputs')]
    public function testInput(mixed $input, ?string $code): void
    {
        $result = new NotNull()->validate($input);
        $this->assertSame($code === null, $result->isValid());
        if ($code !== null) {
            $this->assertCount(1, $result->violations());
            $this->assertSame($code, $result->violations()[0]->code());
            $this->assertSame([], $result->violations()[0]->path());
        } else {
            $this->assertSame([], $result->violations());

        }
    }

    /**
     * @return iterable<array{mixed, ?string}>
     */
    public static function inputs(): iterable
    {
        yield [null, NotNull::CODE_NULL_VALUE];
        yield [false, null];
        yield [0, null];
        yield ['', null];
        yield [[], null];
    }
}
