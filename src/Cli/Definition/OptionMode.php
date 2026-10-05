<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Definition;

/**
 * Describes how a CLI option accepts input.
 */
enum OptionMode
{
    /**
     * The option supplies a boolean flag without a value.
     */
    case Flag;

    /**
     * The option requires a string value when supplied.
     */
    case RequiredValue;
}
