<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Http\Exception;

use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use RuntimeException;
use Throwable;

/**
 * Preserves an HTTP execution failure together with a subsequent application shutdown failure.
 */
final class HttpRunException extends RuntimeException implements IntegrationException
{
    /**
     * Creates a combined failure with the execution failure as its previous exception.
     *
     * @param Throwable $failure The original HTTP execution failure.
     * @param Throwable $shutdownFailure The additional application shutdown failure.
     */
    public function __construct(Throwable $failure, public readonly Throwable $shutdownFailure)
    {
        parent::__construct('HTTP execution failed and application shutdown also failed.', 0, $failure);
    }
}
