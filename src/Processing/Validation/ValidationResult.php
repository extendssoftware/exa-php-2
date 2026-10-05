<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Validation;

use ExtendsSoftware\ExaPHP\Processing\Violation;

use function array_values;

/**
 * Provides a stable validation outcome and its violations.
 */
final readonly class ValidationResult
{
    /**
     * The reported violations in their supplied order.
     *
     * @var list<Violation>
     */
    private array $violations;

    /**
     * Creates a validation result, valid when no violations are supplied.
     *
     * @param Violation ...$violations The violations to retain in order.
     */
    public function __construct(Violation ...$violations)
    {
        $this->violations = array_values($violations);
    }

    /**
     * Reports whether the result contains no violations.
     *
     * @return bool True exactly when violations() returns an empty list.
     */
    public function isValid(): bool
    {
        return $this->violations === [];
    }

    /**
     * Returns the violations reported for the input.
     *
     * @return list<Violation> The violations, or an empty list for valid input.
     */
    public function violations(): array
    {
        return $this->violations;
    }
}
