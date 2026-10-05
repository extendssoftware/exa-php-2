<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Command\Middleware;

use Override;
use Closure;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Command;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;
use Throwable;

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
     * Executes the step with the supplied command and context.
     *
     * @param Command $command The command to execute.
     * @param DispatchContext $context The execution metadata.
     *
     * @return void
     *
     * @throws Throwable When the step fails, propagated unchanged.
     */
    #[Override]
    public function execute(Command $command, DispatchContext $context): void
    {
        ($this->execution)($command, $context);
    }
}
