<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Transaction\Middleware;

use ExtendsSoftware\ExaPHP\Cqrs\Command\Command;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Middleware\CommandExecution;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Middleware\CommandMiddleware;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;
use ExtendsSoftware\ExaPHP\Transaction\TransactionManager;
use Override;
use Throwable;

/**
 * Executes the remaining command pipeline within the injected manager's transaction.
 */
final readonly class TransactionalCommandMiddleware implements CommandMiddleware
{
    /**
     * Creates middleware for the manager's participating resources.
     *
     * @param TransactionManager $transactionManager The manager controlling transaction execution.
     */
    public function __construct(private TransactionManager $transactionManager)
    {
    }

    /**
     * Delegates the remaining pipeline once through transactional execution with unchanged command and context.
     *
     * A nested dispatch through this middleware and the same manager is subject to its nested transaction rejection.
     * Middleware outside this step executes outside its transaction. No events are published or logging performed.
     *
     * @param Command $command The command to execute.
     * @param DispatchContext $context The execution metadata.
     * @param CommandExecution $next The remaining command pipeline.
     *
     * @return void
     *
     * @throws Throwable When transaction management or downstream execution fails, propagated unchanged.
     */
    #[Override]
    public function process(Command $command, DispatchContext $context, CommandExecution $next): void
    {
        $this->transactionManager->transactional(static function () use ($command, $context, $next): void {
            $next->execute($command, $context);
        });
    }
}
