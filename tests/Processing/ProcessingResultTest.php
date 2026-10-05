<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing;

use ExtendsSoftware\ExaPHP\Processing\Exception\ProcessingValueUnavailableException;
use ExtendsSoftware\ExaPHP\Processing\ProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\Violation;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ProcessingResultTest extends TestCase
{
    public function testSuccessfulResultPreservesValue(): void
    {
        $value = new stdClass();
        $result = ProcessingResult::success($value);
        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->violations());
        $this->assertSame($value, $result->value());
    }

    public function testNullIsAValidSuccessfulValue(): void
    {
        $result = ProcessingResult::success(null);
        $this->assertTrue($result->isValid());
        $this->assertNull($result->value());
    }

    public function testFailurePreservesViolationsAndHasNoValue(): void
    {
        $first = new Violation('required', 'Required');
        $second = new Violation('invalid', 'Invalid');
        $result = ProcessingResult::failure($first, $second);
        $this->assertFalse($result->isValid());
        $this->assertSame([$first, $second], $result->violations());
        $this->expectException(ProcessingValueUnavailableException::class);
        $result->value();
    }
}
