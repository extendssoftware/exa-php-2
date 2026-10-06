<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Pdo\Transaction;

use ExtendsSoftware\ExaPHP\Transaction\Exception\NestedTransactionException;
use ExtendsSoftware\ExaPHP\Transaction\Exception\TransactionCommitException;
use ExtendsSoftware\ExaPHP\Transaction\Exception\TransactionRollbackException;
use ExtendsSoftware\ExaPHP\Transaction\Exception\TransactionStartException;
use ExtendsSoftware\ExaPHP\Transaction\TransactionManager;
use Override;
use PDO;
use PDOException;
use Throwable;

/**
 * Executes transactions on one shared PDO connection.
 *
 * Existing connection transactions are rejected. Operations must leave transaction control to this manager and
 * retain exception error mode and avoid statements that implicitly commit. Commit failures are reported without
 * attempting rollback or retry.
 */
final class PdoTransactionManager implements TransactionManager
{
    /**
     * Whether this manager is executing a transaction operation.
     */
    private bool $active = false;

    /**
     * Creates a manager for the connection shared with application persistence.
     *
     * @param PDO $pdo The participating connection.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Executes the operation once and returns its result after successful commit.
     *
     * @template TResult
     *
     * @param callable(): TResult $operation The operation using the shared connection.
     *
     * @return TResult The committed operation result.
     *
     * @throws NestedTransactionException When this manager or connection already has an active transaction.
     * @throws TransactionStartException When exception mode is not enabled or transaction startup fails.
     * @throws TransactionCommitException When commit cannot be confirmed.
     * @throws TransactionRollbackException When rollback after an operation failure also fails.
     * @throws Throwable When the operation fails and rollback succeeds, propagated unchanged.
     */
    #[Override]
    public function transactional(callable $operation): mixed
    {
        if ($this->active) {
            throw new NestedTransactionException('The PDO transaction manager is already active.');
        }

        try {
            if ($this->pdo->inTransaction()) {
                throw new NestedTransactionException('The PDO connection already has an active transaction.');
            }
            if ($this->pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
                throw new TransactionStartException('PDO transactions require PDO::ERRMODE_EXCEPTION.');
            }
            $started = $this->pdo->beginTransaction();
        } catch (PDOException $exception) {
            throw new TransactionStartException('Unable to start the PDO transaction.', 0, $exception);
        }
        if (!$started) {
            throw new TransactionStartException('PDO did not start the transaction.');
        }

        $this->active = true;
        try {
            try {
                $result = $operation();
            } catch (Throwable $failure) {
                try {
                    if (!$this->pdo->rollBack()) {
                        throw new PDOException('PDO did not roll back the transaction.');
                    }
                } catch (PDOException $rollbackFailure) {
                    throw new TransactionRollbackException($failure, $rollbackFailure);
                }

                throw $failure;
            }

            try {
                $committed = $this->pdo->commit();
            } catch (PDOException $exception) {
                throw new TransactionCommitException('PDO transaction commit was not confirmed.', 0, $exception);
            }
            if (!$committed) {
                throw new TransactionCommitException('PDO transaction commit was not confirmed.');
            }

            return $result;
        } finally {
            $this->active = false;
        }
    }
}
