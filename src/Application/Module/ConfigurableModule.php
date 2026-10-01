<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Module;

/**
 * Provides a module configuration directory.
 */
interface ConfigurableModule
{
    /**
     * Returns the absolute path to the module configuration directory.
     *
     * @return non-empty-string The absolute configuration directory path.
     */
    public function configDirectory(): string;
}
