<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing;

use Override;

/**
 * Holds a successfully processed value without violations.
 *
 * @template-covariant TValue
 * @implements ProcessingResult<TValue>
 */
final readonly class SuccessfulProcessingResult implements ProcessingResult
{
    /**
     * Creates a successful result, retaining the value without cloning or freezing it.
     *
     * @param TValue $value The processed value, including null.
     */
    public function __construct(private mixed $value)
    {
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function isValid(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function violations(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function value(): mixed
    {
        return $this->value;
    }
}
