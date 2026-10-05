<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Transaction;

use ExtendsSoftware\ExaPHP\Transaction\Exception\NestedTransactionException;
use ExtendsSoftware\ExaPHP\Transaction\Exception\TransactionCommitException;
use ExtendsSoftware\ExaPHP\Transaction\Exception\TransactionRollbackException;
use ExtendsSoftware\ExaPHP\Transaction\Exception\TransactionStartException;
use Throwable;

/**
 * Executes an operation within one transaction over the manager's participating resources.
 */
interface TransactionManager
{
    /**
     * Starts a transaction, invokes the operation once, commits, and returns its result.
     *
     * The operation receives no arguments and runs only after successful startup. Its result is returned only after
     * successful commit. If it throws any Throwable, rollback is attempted; successful rollback propagates that same
     * failure unchanged. A rollback failure preserves both failures in TransactionRollbackException.
     *
     * Nested calls on this manager are rejected before invoking their operation, leaving the enclosing transaction
     * untouched by that rejection. If the rejection escapes the enclosing operation, normal rollback applies.
     * Commit failures must report that commit was not confirmed; callers cannot assume that changes were rolled back.
     * Lower-level lifecycle failures are translated into transaction exceptions with their original cause retained.
     *
     * @template TResult
     *
     * @param callable(): TResult $operation The operation participating in the transaction.
     *
     * @return TResult The operation result after successful commit.
     *
     * @throws NestedTransactionException When a transaction is already active on this manager.
     * @throws TransactionStartException When a transaction cannot be started.
     * @throws TransactionCommitException When commit cannot be confirmed.
     * @throws TransactionRollbackException When rollback after an operation failure also fails.
     * @throws Throwable When the operation fails and rollback succeeds, propagated unchanged.
     */
    public function transactional(callable $operation): mixed;
}
