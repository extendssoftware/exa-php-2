<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Transformation\Shape;

/**
 * Determines how fields outside a shape are handled.
 */
enum UnknownFields
{
    /**
     * Reports a violation for each unknown field.
     */
    case Reject;

    /**
     * Copies unknown fields without processing their values.
     */
    case Preserve;

    /**
     * Omits unknown fields from the output.
     */
    case Discard;
}
