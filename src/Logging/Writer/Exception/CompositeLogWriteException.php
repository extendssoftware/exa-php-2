<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Logging\Writer\Exception;

use ExtendsSoftware\ExaPHP\Logging\LoggingException;
use RuntimeException;

use function count;
use function reset;
use function sprintf;

/**
 * Reports logging failures collected while delivering a record to multiple writers.
 */
final class CompositeLogWriteException extends RuntimeException implements LoggingException
{
    /**
     * Creates a delivery failure retaining the first failure as its previous exception.
     *
     * @param non-empty-array<int, LoggingException> $failures Failures keyed by zero-based writer position.
     */
    public function __construct(public readonly array $failures)
    {
        parent::__construct(
            sprintf('Log delivery failed for %d writer(s).', count($failures)),
            0,
            reset($failures),
        );
    }
}
