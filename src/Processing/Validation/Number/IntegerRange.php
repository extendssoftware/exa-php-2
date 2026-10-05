<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Validation\Number;

use ExtendsSoftware\ExaPHP\Processing\Exception\InvalidIntegerRangeException;
use ExtendsSoftware\ExaPHP\Processing\Validation\ValidationResult;
use ExtendsSoftware\ExaPHP\Processing\Validation\Validator;
use ExtendsSoftware\ExaPHP\Processing\Violation;

use function is_int;

/**
 * Requires an integer within inclusive bounds.
 *
 * @implements Validator<mixed>
 */
final readonly class IntegerRange implements Validator
{
    /**
     * The input is not an integer.
     */
    public const string CODE_NOT_INTEGER = 'not_integer';

    /**
     * The integer falls outside the configured inclusive bounds.
     */
    public const string CODE_INTEGER_OUT_OF_RANGE = 'integer_out_of_range';

    /**
     * Creates an inclusive integer range.
     *
     * @param int $minimum The lower bound.
     * @param int $maximum The upper bound.
     *
     * @throws InvalidIntegerRangeException When the minimum exceeds the maximum.
     */
    public function __construct(private int $minimum, private int $maximum)
    {
        if ($minimum > $maximum) {
            throw new InvalidIntegerRangeException('The minimum must not exceed the maximum.');
        }
    }

    /**
     * Checks the integer type and inclusive bounds without coercion.
     *
     * @param mixed $value The input value.
     *
     * @return ValidationResult The outcome, with not_integer or integer_out_of_range violations on failure.
     */
    public function validate(mixed $value): ValidationResult
    {
        if (!is_int($value)) {
            return new ValidationResult(new Violation(self::CODE_NOT_INTEGER, 'Value must be an integer.'));
        }
        if ($value < $this->minimum || $value > $this->maximum) {
            return new ValidationResult(
                new Violation(
                    self::CODE_INTEGER_OUT_OF_RANGE,
                    'Value must be within the inclusive integer range.',
                    parameters: ['minimum' => $this->minimum, 'maximum' => $this->maximum],
                ),
            );
        }

        return new ValidationResult();
    }
}
