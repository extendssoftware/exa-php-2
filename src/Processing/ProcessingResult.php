<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing;

use ExtendsSoftware\ExaPHP\Processing\Exception\ProcessingValueUnavailableException;
use ExtendsSoftware\ExaPHP\Processing\Validation\ValidationResult;

/**
 * Holds either a successfully processed value or violations explaining rejected input.
 *
 * Values and nested objects are retained without cloning or freezing them.
 *
 * @template-covariant TValue
 */
final readonly class ProcessingResult
{
    /**
     * Creates a result through its success or failure factory.
     *
     * @param TValue|null $value The successful value, or null for failure.
     * @param ValidationResult $validation The validation outcome.
     */
    private function __construct(private mixed $value, private ValidationResult $validation)
    {
    }

    /**
     * Creates a successful result, including when the value is null.
     *
     * @template T
     * @param T $value The processed value.
     *
     * @return ProcessingResult<T> The successful result.
     */
    public static function success(mixed $value): self
    {
        return new self($value, new ValidationResult());
    }

    /**
     * Creates an unsuccessful result requiring at least one violation.
     *
     * @param Violation $violation The first violation.
     * @param Violation ...$violations Additional violations in reporting order.
     *
     * @return ProcessingResult<never> The unsuccessful result.
     */
    public static function failure(Violation $violation, Violation ...$violations): self
    {
        return new self(null, new ValidationResult($violation, ...$violations));
    }

    /**
     * Reports whether processing succeeded.
     *
     * @return bool True exactly when the result has no violations.
     */
    public function isValid(): bool
    {
        return $this->validation->isValid();
    }

    /**
     * Returns the input violations in reporting order.
     *
     * @return list<Violation> The violations, or an empty list for success.
     */
    public function violations(): array
    {
        return $this->validation->violations();
    }

    /**
     * Returns the successfully processed value.
     *
     * @return TValue The value, which may be null.
     *
     * @throws ProcessingValueUnavailableException When processing failed and no successful value is available.
     */
    public function value(): mixed
    {
        if (!$this->isValid()) {
            throw new ProcessingValueUnavailableException('An unsuccessful processing result has no value.');
        }

        return $this->value;
    }
}
