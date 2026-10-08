<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Command\Middleware;

use Override;
use Closure;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Command;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;

/**
 * Adapts a pipeline closure to command execution.
 *
 * @internal
 */
final readonly class ClosureCommandExecution implements CommandExecution
{
    /**
     * Creates an execution step.
     *
     * @param Closure(Command, DispatchContext): void $execution The step to execute.
     */
    public function __construct(private Closure $execution)
    {
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function execute(Command $command, DispatchContext $context): void
    {
        ($this->execution)($command, $context);
    }
}
