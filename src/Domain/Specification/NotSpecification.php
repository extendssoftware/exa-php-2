<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Domain\Specification;

use Override;

/**
 * Negates a condition.
 *
 * @template T of object
 * @extends AbstractSpecification<T>
 */
final class NotSpecification extends AbstractSpecification
{
    /**
     * Creates the composite without evaluating its operands.
     *
     * @param Specification<T> $specification The condition to negate.
     */
    public function __construct(private readonly Specification $specification)
    {
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function isSatisfiedBy(object $candidate): bool
    {
        return !$this->specification->isSatisfiedBy($candidate);
    }
}
