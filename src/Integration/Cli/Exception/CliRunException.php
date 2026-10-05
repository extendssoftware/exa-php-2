<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Cli\Exception;

use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use RuntimeException;
use Throwable;

/**
 * Preserves a CLI execution failure together with a subsequent application shutdown failure.
 */
final class CliRunException extends RuntimeException implements IntegrationException
{
    /**
     * Creates a combined failure with the execution failure as its previous exception.
     *
     * @param Throwable $failure The original CLI execution failure.
     * @param Throwable $shutdownFailure The additional application shutdown failure.
     */
    public function __construct(Throwable $failure, public readonly Throwable $shutdownFailure)
    {
        parent::__construct('CLI execution failed and application shutdown also failed.', 0, $failure);
    }
}
