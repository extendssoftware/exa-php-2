<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Transformation\Shape;

/**
 * Determines whether an absent field is allowed.
 */
enum FieldPresence
{
    /**
     * Reports a violation when the field is absent.
     */
    case Required;

    /**
     * Omits an absent field from the output.
     */
    case Optional;
}
