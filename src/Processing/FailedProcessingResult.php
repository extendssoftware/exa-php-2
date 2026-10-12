<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing;

use ExtendsSoftware\ExaPHP\Processing\Exception\ProcessingValueUnavailableException;
use ExtendsSoftware\ExaPHP\Processing\Validation\ValidationResult;
use Override;

/**
 * Holds violations explaining rejected input without a successful value.
 *
 * @implements ProcessingResult<never>
 */
final readonly class FailedProcessingResult implements ProcessingResult
{
    /**
     * The failed validation outcome.
     *
     * @var ValidationResult
     */
    private ValidationResult $validation;

    /**
     * Creates an unsuccessful result requiring at least one violation.
     *
     * @param Violation $violation The first violation.
     * @param Violation ...$violations Additional violations in reporting order.
     */
    public function __construct(Violation $violation, Violation ...$violations)
    {
        $this->validation = new ValidationResult($violation, ...$violations);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function isValid(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function violations(): array
    {
        return $this->validation->violations();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function value(): never
    {
        throw new ProcessingValueUnavailableException('An unsuccessful processing result has no value.');
    }
}
