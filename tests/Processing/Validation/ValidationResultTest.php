<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing\Validation;

use ExtendsSoftware\ExaPHP\Processing\Validation\ValidationResult;
use ExtendsSoftware\ExaPHP\Processing\Violation;
use PHPUnit\Framework\TestCase;

final class ValidationResultTest extends TestCase
{
    public function testEmptyResultIsValid(): void
    {
        $result = new ValidationResult();
        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->violations());
    }

    public function testViolationsRetainOrderAndIdentity(): void
    {
        $first = new Violation('required', 'Required');
        $second = new Violation('invalid', 'Invalid');
        $result = new ValidationResult($first, $second);
        $this->assertFalse($result->isValid());
        $this->assertSame([$first, $second], $result->violations());
    }
}
