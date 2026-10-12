<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Validation;

use ExtendsSoftware\ExaPHP\Processing\Violation;
use Override;

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
     * {@inheritDoc}
     *
     * Null produces a `null_value` violation.
     */
    #[Override]
    public function validate(mixed $value): ValidationResult
    {
        return $value === null
            ? new ValidationResult(new Violation(self::CODE_NULL_VALUE, 'Value must not be null.'))
            : new ValidationResult();
    }
}
