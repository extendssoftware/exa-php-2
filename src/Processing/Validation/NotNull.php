<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Validation;

use Override;
use ExtendsSoftware\ExaPHP\Processing\Violation;

/**
 * Rejects null while accepting every other value.
 *
 * @implements Validator<mixed>
 */
final readonly class NotNull implements Validator
{
    /**
     * The input is null.
     */
    public const string CODE_NULL_VALUE = 'null_value';

    /**
     * Checks whether the input is not null.
     *
     * @param mixed $value The input value.
     *
     * @return ValidationResult The outcome, with a null_value violation for null.
     */
    #[Override]
    public function validate(mixed $value): ValidationResult
    {
        return $value === null
            ? new ValidationResult(new Violation(self::CODE_NULL_VALUE, 'Value must not be null.'))
            : new ValidationResult();
    }
}
