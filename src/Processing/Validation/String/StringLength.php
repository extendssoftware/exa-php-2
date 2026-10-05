<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Validation\String;

use Override;
use ExtendsSoftware\ExaPHP\Processing\Validation\ValidationResult;
use ExtendsSoftware\ExaPHP\Processing\Validation\Validator;
use ExtendsSoftware\ExaPHP\Processing\Violation;
use ExtendsSoftware\ExaPHP\Processing\Validation\Exception\InvalidStringLengthException;
use ExtendsSoftware\ExaPHP\Processing\Validation\Exception\PatternExecutionException;

use function is_string;
use function preg_match_all;
use function preg_last_error;
use function preg_last_error_msg;

use const PREG_BAD_UTF8_ERROR;

/**
 * Checks inclusive UTF-8 code-point length without normalization.
 *
 * @implements Validator<mixed>
 */
final readonly class StringLength implements Validator
{
    /**
     * The input is not a string.
     */
    public const string CODE_NOT_STRING = 'not_string';

    /**
     * The string is not valid UTF-8.
     */
    public const string CODE_INVALID_UTF8 = 'invalid_utf8';

    /**
     * The code-point count is outside the configured bounds.
     */
    public const string CODE_STRING_LENGTH_OUT_OF_RANGE = 'string_length_out_of_range';

    /**
     * Creates an inclusive code-point length constraint.
     *
     * @param int $minimum The non-negative minimum length.
     * @param int $maximum The maximum length, at least the minimum.
     *
     * @throws InvalidStringLengthException When the bounds are negative or reversed.
     */
    public function __construct(private int $minimum, private int $maximum)
    {
        if ($minimum < 0 || $maximum < $minimum) {
            throw new InvalidStringLengthException('String length bounds must be non-negative and ordered.');
        }
    }

    /**
     * Counts Unicode code points, including newlines, rather than bytes or grapheme clusters.
     *
     * @param mixed $value The input value.
     *
     * @return ValidationResult The type, encoding, or length violations, or a valid result.
     *
     * @throws PatternExecutionException When the counting expression cannot execute.
     */
    #[Override]
    public function validate(mixed $value): ValidationResult
    {
        if (!is_string($value)) {
            return new ValidationResult(new Violation(self::CODE_NOT_STRING, 'Value must be a string.'));
        }
        $length = preg_match_all('/./us', $value);
        if ($length === false) {
            if (preg_last_error() === PREG_BAD_UTF8_ERROR) {
                return new ValidationResult(new Violation(self::CODE_INVALID_UTF8, 'Value must be valid UTF-8.'));
            }
            throw new PatternExecutionException('String length counting failed: ' . preg_last_error_msg());
        }
        if ($length < $this->minimum || $length > $this->maximum) {
            return new ValidationResult(new Violation(
                self::CODE_STRING_LENGTH_OUT_OF_RANGE,
                'String length must be within the inclusive range.',
                parameters: ['minimum' => $this->minimum, 'maximum' => $this->maximum, 'length' => $length],
            ));
        }

        return new ValidationResult();
    }
}
