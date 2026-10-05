<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Module\Exception;

use ExtendsSoftware\ExaPHP\Application\ApplicationException;
use ExtendsSoftware\ExaPHP\Application\Module\Module;
use RuntimeException;
use Throwable;

use function array_keys;
use function implode;
use function reset;
use function sprintf;

/**
 * Reports shutdown hook failures after all eligible modules have been processed.
 */
final class ModuleShutdownException extends RuntimeException implements ApplicationException
{
    /**
     * Creates a shutdown failure with every hook failure and the first failure as its previous exception.
     *
     * @param non-empty-array<class-string<Module>, Throwable> $failures Failures in shutdown order by module class.
     */
    public function __construct(public readonly array $failures)
    {
        parent::__construct(
            sprintf('Shutdown hooks failed for modules: %s.', implode(', ', array_keys($failures))),
            0,
            reset($failures),
        );
    }
}
