<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Transaction\Exception;

use ExtendsSoftware\ExaPHP\Transaction\TransactionException;
use RuntimeException;
use Throwable;

/**
 * Preserves an operation failure together with a subsequent rollback failure.
 */
final class TransactionRollbackException extends RuntimeException implements TransactionException
{
    /**
     * Creates a combined failure with the operation failure as its previous exception.
     *
     * @param Throwable $failure The original operation failure.
     * @param Throwable $rollbackFailure The additional rollback failure.
     */
    public function __construct(Throwable $failure, public readonly Throwable $rollbackFailure)
    {
        parent::__construct('Transaction operation failed and rollback also failed.', 0, $failure);
    }
}
