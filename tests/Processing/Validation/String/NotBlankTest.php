<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing\Validation\String;

use ExtendsSoftware\ExaPHP\Processing\Validation\String\NotBlank;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NotBlankTest extends TestCase
{
    public function testViolationCodesRemainStable(): void
    {
        $this->assertSame('not_string', NotBlank::CODE_NOT_STRING);
        $this->assertSame('blank_string', NotBlank::CODE_BLANK_STRING);
    }

    #[DataProvider('inputs')]
    public function testInput(mixed $input, ?string $code): void
    {
        $result = new NotBlank()->validate($input);
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
        yield ['', NotBlank::CODE_BLANK_STRING];
        yield [" \t\n\r\0\v", NotBlank::CODE_BLANK_STRING];
        yield ['0', null];
        yield [' text ', null];
        yield ["\u{00A0}", null];
        yield [null, NotBlank::CODE_NOT_STRING];
        yield [0, NotBlank::CODE_NOT_STRING];
    }
}
