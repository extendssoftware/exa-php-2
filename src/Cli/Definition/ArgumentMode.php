<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Definition;

/**
 * Describes how a CLI argument accepts input.
 */
enum ArgumentMode
{
    /**
     * The positional argument must be supplied.
     */
    case Required;

    /**
     * The positional argument may be omitted.
     */
    case Optional;
}
