<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Domain\Specification;

/**
 * Provides fluent composition for domain specifications.
 *
 * Composition creates new specifications without evaluating or modifying the operands.
 *
 * @template T of object
 * @implements Specification<T>
 */
abstract class AbstractSpecification implements Specification
{
    /**
     * Requires both specifications to be satisfied.
     *
     * @param Specification<T> $other The specification to combine with this one.
     *
     * @return AbstractSpecification<T> The combined specification.
     */
    final public function and(Specification $other): AbstractSpecification
    {
        return new AndSpecification($this, $other);
    }

    /**
     * Requires at least one specification to be satisfied.
     *
     * @param Specification<T> $other The specification to combine with this one.
     *
     * @return AbstractSpecification<T> The combined specification.
     */
    final public function or(Specification $other): AbstractSpecification
    {
        return new OrSpecification($this, $other);
    }

    /**
     * Negates this specification.
     *
     * @return AbstractSpecification<T> The negated specification.
     */
    final public function not(): AbstractSpecification
    {
        return new NotSpecification($this);
    }
}
