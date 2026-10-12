<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing;

use ExtendsSoftware\ExaPHP\Processing\Exception\ProcessingValueUnavailableException;

/**
 * Holds either a successfully processed value or violations explaining rejected input.
 *
 * Values and nested objects are retained without cloning or freezing them.
 *
 * @template-covariant TValue
 */
interface ProcessingResult
{
    /**
     * Reports whether processing succeeded.
     *
     * @return bool True exactly when the result has no violations.
     */
    public function isValid(): bool;

    /**
     * Returns the input violations in reporting order.
     *
     * @return list<Violation> The violations, or an empty list for success.
     */
    public function violations(): array;

    /**
     * Returns the successfully processed value.
     *
     * @return TValue The value, which may be null.
     *
     * @throws ProcessingValueUnavailableException When processing failed and no successful value is available.
     */
    public function value(): mixed;
}
