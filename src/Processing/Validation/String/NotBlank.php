<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Validation\String;

use Override;
use ExtendsSoftware\ExaPHP\Processing\Validation\ValidationResult;
use ExtendsSoftware\ExaPHP\Processing\Validation\Validator;
use ExtendsSoftware\ExaPHP\Processing\Violation;

use function is_string;
use function trim;

/**
 * Requires a string that is non-empty after PHP default trim characters are removed.
 *
 * @implements Validator<mixed>
 */
final readonly class NotBlank implements Validator
{
    /**
     * The input is not a string.
     */
    public const string CODE_NOT_STRING = 'not_string';

    /**
     * The string is empty after trimming.
     */
    public const string CODE_BLANK_STRING = 'blank_string';

    /**
     * {@inheritDoc}
     *
     * Non-strings produce `not_string`; blank strings produce `blank_string`. The input is not modified.
     */
    #[Override]
    public function validate(mixed $value): ValidationResult
    {
        if (!is_string($value)) {
            return new ValidationResult(new Violation(self::CODE_NOT_STRING, 'Value must be a string.'));
        }

        return trim($value) === ''
            ? new ValidationResult(new Violation(self::CODE_BLANK_STRING, 'Value must not be blank.'))
            : new ValidationResult();
    }
}
