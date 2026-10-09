<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Domain\Specification;

use Override;

/**
 * Requires both conditions, evaluating the right operand only when the left succeeds.
 *
 * @template T of object
 * @extends AbstractSpecification<T>
 */
final class AndSpecification extends AbstractSpecification
{
    /**
     * Creates the composite without evaluating its operands.
     *
     * @param Specification<T> $left The first condition to evaluate.
     * @param Specification<T> $right The second condition to evaluate when needed.
     */
    public function __construct(private readonly Specification $left, private readonly Specification $right)
    {
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function isSatisfiedBy(object $candidate): bool
    {
        return $this->left->isSatisfiedBy($candidate) && $this->right->isSatisfiedBy($candidate);
    }
}
