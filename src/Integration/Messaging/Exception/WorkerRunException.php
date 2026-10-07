<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Messaging\Exception;

use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use RuntimeException;
use Throwable;

/**
 * Preserves a messaging worker execution failure together with a subsequent worker control cleanup failure.
 */
final class WorkerRunException extends RuntimeException implements IntegrationException
{
    /**
     * Creates a combined failure with the execution failure as its previous exception.
     *
     * @param Throwable $failure The original messaging worker execution failure.
     * @param Throwable $cleanupFailure The additional worker control cleanup failure.
     */
    public function __construct(Throwable $failure, public readonly Throwable $cleanupFailure)
    {
        parent::__construct('Messaging worker execution failed and worker control cleanup also failed.', 0, $failure);
    }
}
