<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Transaction\Middleware;

use ExtendsSoftware\ExaPHP\Cqrs\Command\Command;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Middleware\CommandExecution;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;
use ExtendsSoftware\ExaPHP\Integration\Transaction\Middleware\TransactionalCommandMiddleware;
use ExtendsSoftware\ExaPHP\Transaction\Exception\NestedTransactionException;
use ExtendsSoftware\ExaPHP\Transaction\Exception\TransactionStartException;
use ExtendsSoftware\ExaPHP\Transaction\TransactionManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use TypeError;

final class TransactionalCommandMiddlewareTest extends TestCase
{
    public function testExecutesDownstreamOnlyInsideManagerCallbackWithOriginalInputs(): void
    {
        $command = $this->createStub(Command::class);
        $context = new DispatchContext();
        $inside = false;
        $next = $this->createMock(CommandExecution::class);
        $next->expects($this->once())->method('execute')->with($command, $context)->willReturnCallback(
            function () use (&$inside): void {
                $this->assertTrue($inside);
            },
        );
        $manager = $this->createMock(TransactionManager::class);
        $manager->expects($this->once())->method('transactional')->willReturnCallback(
            static function (callable $operation) use (&$inside): mixed {
                $inside = true;
                try {
                    return $operation();
                } finally {
                    $inside = false;
                }
            },
        );
        new TransactionalCommandMiddleware($manager)->process($command, $context, $next);
        $this->assertFalse($inside);
    }

    #[DataProvider('managerFailures')]
    public function testManagerFailureDoesNotExecuteDownstream(Throwable $failure): void
    {
        $manager = $this->createMock(TransactionManager::class);
        $manager->expects($this->once())->method('transactional')->willThrowException($failure);
        $next = $this->createMock(CommandExecution::class);
        $next->expects($this->never())->method('execute');
        try {
            new TransactionalCommandMiddleware($manager)->process(
                $this->createStub(Command::class),
                new DispatchContext(),
                $next,
            );
            $this->fail('Expected transaction failure.');
        } catch (Throwable $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    /** @return iterable<array{Throwable}> */
    public static function managerFailures(): iterable
    {
        yield [new TransactionStartException('start failed')];
        yield [new NestedTransactionException('nested transaction')];
    }

    public function testDownstreamEngineFailureReachesManagerAndCallerUnchanged(): void
    {
        $failure = new TypeError('handler failed');
        $next = $this->createStub(CommandExecution::class);
        $next->method('execute')->willThrowException($failure);
        $manager = $this->createMock(TransactionManager::class);
        $manager->expects($this->once())->method('transactional')->willReturnCallback(
            function (callable $operation) use ($failure): mixed {
                try {
                    return $operation();
                } catch (Throwable $exception) {
                    $this->assertSame($failure, $exception);
                    throw $exception;
                }
            },
        );
        try {
            new TransactionalCommandMiddleware($manager)->process(
                $this->createStub(Command::class),
                new DispatchContext(),
                $next,
            );
            $this->fail('Expected handler failure.');
        } catch (TypeError $exception) {
            $this->assertSame($failure, $exception);
        }
    }
}
