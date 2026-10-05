<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Validation;

use ExtendsSoftware\ExaPHP\Processing\ProcessingException;
use Throwable;

use function array_values;

/**
 * Collects violations from all validators applied to the same input.
 *
 * @template TInput
 * @implements Validator<TInput>
 */
final readonly class AllOf implements Validator
{
    /**
     * The validators in evaluation order.
     *
     * @var list<Validator<TInput>>
     */
    private array $validators;

    /**
     * Creates a composite from independent validators accepting the same input type.
     *
     * @param Validator<TInput> ...$validators Validators in evaluation order; repeated instances execute repeatedly.
     */
    public function __construct(Validator ...$validators)
    {
        $this->validators = array_values($validators);
    }

    /**
     * Evaluates every validator and retains all violations in reporting order.
     *
     * Violations retain identity, paths, and duplicates. An empty composite succeeds. Exceptions stop evaluation
     * immediately and propagate unchanged rather than becoming violations.
     *
     * @param TInput $value The unchanged input supplied to every validator.
     *
     * @return ValidationResult The combined violations, empty when every validator succeeds.
     *
     * @throws ProcessingException When a validator reports a configuration or execution failure.
     * @throws Throwable When a validator throws an unexpected exception or error.
     */
    public function validate(mixed $value): ValidationResult
    {
        $violations = [];
        foreach ($this->validators as $validator) {
            foreach ($validator->validate($value)->violations() as $violation) {
                $violations[] = $violation;
            }
        }

        return new ValidationResult(...$violations);
    }
}
