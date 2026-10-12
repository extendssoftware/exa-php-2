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
        self::assertTrue($result->isValid());
        self::assertSame([], $result->violations());
    }

    public function testViolationsRetainOrderAndIdentity(): void
    {
        $first = new Violation('required', 'Required');
        $second = new Violation('invalid', 'Invalid');
        $result = new ValidationResult($first, $second);
        self::assertFalse($result->isValid());
        self::assertSame([$first, $second], $result->violations());
    }
}
