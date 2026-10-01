<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Ddd\Specification;

use Throwable;

/**
 * Evaluates whether an object satisfies a condition without changing it.
 *
 * @template T of object
 */
interface Specification
{
    /**
     * Checks whether the candidate satisfies the condition.
     *
     * @param T $candidate The object to evaluate.
     *
     * @return bool Whether the condition is satisfied.
     *
     * @throws Throwable When evaluation fails.
     */
    public function isSatisfiedBy(object $candidate): bool;
}
