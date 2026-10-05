<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Validation;

use ExtendsSoftware\ExaPHP\Processing\ProcessingException;

/**
 * Checks input without changing it or its nested objects.
 *
 * @template TInput
 */
interface Validator
{
    /**
     * Reports violations for invalid input without throwing for ordinary validation failures.
     *
     * @param TInput $value The input value to check.
     *
     * @return ValidationResult The outcome containing any violations.
     *
     * @throws ProcessingException When configuration or execution prevents validation.
     */
    public function validate(mixed $value): ValidationResult;
}
