<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Transaction\Exception;

use ExtendsSoftware\ExaPHP\Transaction\Exception\TransactionRollbackException;
use ExtendsSoftware\ExaPHP\Transaction\TransactionException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;

final class TransactionRollbackExceptionTest extends TestCase
{
    public function testPreservesOperationAndRollbackFailures(): void
    {
        $operation = new TypeError('operation failed');
        $rollback = new RuntimeException('connection lost');
        $failure = new TransactionRollbackException($operation, $rollback);
        $this->assertInstanceOf(TransactionException::class, $failure);
        $this->assertSame($operation, $failure->getPrevious());
        $this->assertSame($rollback, $failure->rollbackFailure);
        $this->assertSame('Transaction operation failed and rollback also failed.', $failure->getMessage());
    }
}
