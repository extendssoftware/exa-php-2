<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Validation;

use Override;
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
     * {@inheritDoc}
     *
     * Violations retain identity, paths, duplicates, and reporting order. An empty composite succeeds.
     * Exceptions stop evaluation immediately.
     *
     * @throws Throwable When a validator throws an unexpected exception or error.
     */
    #[Override]
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
