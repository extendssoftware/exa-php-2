<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Transformation\Collection;

/**
 * Determines accepted array keys for collection processing.
 */
enum CollectionKeys
{
    /**
     * Accepts and preserves all PHP array keys.
     */
    case Preserve;

    /**
     * Requires consecutive integer keys starting at zero.
     */
    case RequireList;
}
