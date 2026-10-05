<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Module\Exception;

use ExtendsSoftware\ExaPHP\Application\ApplicationException;
use ExtendsSoftware\ExaPHP\Application\Module\Module;
use RuntimeException;
use Throwable;

use function sprintf;

/**
 * Reports a failed bootstrap hook and any failures while cleaning up previously started modules.
 */
final class ModuleBootstrapException extends RuntimeException implements ApplicationException
{
    /**
     * Creates a bootstrap failure preserving its original cause and cleanup failures.
     *
     * @param class-string<Module> $module The module whose bootstrap hook failed.
     * @param Throwable $failure The original bootstrap hook failure.
     * @param array<class-string<Module>, Throwable> $shutdownFailures Cleanup failures indexed by module class.
     */
    public function __construct(
        public readonly string $module,
        Throwable $failure,
        public readonly array $shutdownFailures = [],
    ) {
        parent::__construct(sprintf('Bootstrap hook failed for module "%s".', $module), 0, $failure);
    }
}
