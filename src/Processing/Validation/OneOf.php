<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Validation;

use Override;
use ExtendsSoftware\ExaPHP\Processing\Violation;

use function array_values;
use function in_array;

/**
 * Requires strict membership in an explicit set of allowed values.
 *
 * @implements Validator<mixed>
 */
final readonly class OneOf implements Validator
{
    /**
     * The input is not one of the allowed values.
     */
    public const string CODE_NOT_ONE_OF = 'not_one_of';

    /**
     * The allowed values, retained without cloning nested objects.
     *
     * @var list<mixed>
     */
    private array $values;

    /**
     * Creates a membership constraint using PHP strict equality.
     *
     * @param mixed ...$values Allowed values; an empty set rejects every input.
     */
    public function __construct(mixed ...$values)
    {
        $this->values = array_values($values);
    }

    /**
     * Checks membership without coercion; objects match by identity and arrays by strict equality.
     *
     * @param mixed $value The input value.
     *
     * @return ValidationResult A not_one_of violation or a valid result.
     */
    #[Override]
    public function validate(mixed $value): ValidationResult
    {
        return in_array($value, $this->values, true)
            ? new ValidationResult()
            : new ValidationResult(new Violation(self::CODE_NOT_ONE_OF, 'Value is not one of the allowed values.'));
    }
}
