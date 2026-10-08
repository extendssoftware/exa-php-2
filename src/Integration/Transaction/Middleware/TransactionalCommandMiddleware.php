<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Transaction\Middleware;

use ExtendsSoftware\ExaPHP\Cqrs\Command\Command;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Middleware\CommandExecution;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Middleware\CommandMiddleware;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;
use ExtendsSoftware\ExaPHP\Transaction\TransactionManager;
use Override;

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
     * {@inheritDoc}
     *
     * Delegates once with unchanged command and context. Nested dispatch through the same manager is subject to its
     * nested transaction rejection. Middleware outside this step executes outside the transaction.
     */
    #[Override]
    public function process(Command $command, DispatchContext $context, CommandExecution $next): void
    {
        $this->transactionManager->transactional(static function () use ($command, $context, $next): void {
            $next->execute($command, $context);
        });
    }
}
