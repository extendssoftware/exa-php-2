<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing;

use ExtendsSoftware\ExaPHP\Processing\Exception\ProcessingValueUnavailableException;
use ExtendsSoftware\ExaPHP\Processing\FailedProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\SuccessfulProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\Violation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ProcessingResultTest extends TestCase
{
    public function testSuccessfulResultPreservesValue(): void
    {
        $value = new stdClass();
        $result = new SuccessfulProcessingResult($value);
        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->violations());
        $this->assertSame($value, $result->value());
    }

    #[DataProvider('emptyValues')]
    public function testEmptyValuesAreValidSuccessfulValues(mixed $value): void
    {
        $result = new SuccessfulProcessingResult($value);
        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->violations());
        $this->assertSame($value, $result->value());
    }

    /** @return iterable<string, array{mixed}> */
    public static function emptyValues(): iterable
    {
        yield 'null' => [null];
        yield 'false' => [false];
        yield 'zero' => [0];
        yield 'empty string' => [''];
        yield 'empty array' => [[]];
    }

    public function testFailurePreservesViolationsAndHasNoValue(): void
    {
        $first = new Violation('required', 'Required');
        $second = new Violation('invalid', 'Invalid');
        $result = new FailedProcessingResult($first, $second);
        $this->assertFalse($result->isValid());
        $this->assertSame([$first, $second], $result->violations());
        $this->expectException(ProcessingValueUnavailableException::class);
        $result->value();
    }
}
