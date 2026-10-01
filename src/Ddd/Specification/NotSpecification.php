<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Ddd\Specification;

use Throwable;

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
     * Evaluates the condition for the supplied candidate.
     *
     * @param T $candidate The object to evaluate.
     *
     * @return bool Whether the composite condition is satisfied.
     *
     * @throws Throwable When an evaluated operand fails, propagated unchanged.
     */
    public function isSatisfiedBy(object $candidate): bool
    {
        return !$this->specification->isSatisfiedBy($candidate);
    }
}
