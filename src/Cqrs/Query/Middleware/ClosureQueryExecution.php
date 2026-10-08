<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Query\Middleware;

use Override;
use Closure;
use ExtendsSoftware\ExaPHP\Cqrs\Query\Query;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;

/**
 * Adapts a pipeline closure to query execution.
 *
 * @internal
 */
final readonly class ClosureQueryExecution implements QueryExecution
{
    /**
     * Creates an execution step.
     *
     * @param Closure(Query<mixed>, DispatchContext): mixed $execution The step to execute.
     */
    public function __construct(private Closure $execution)
    {
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function execute(Query $query, DispatchContext $context): mixed
    {
        return ($this->execution)($query, $context);
    }
}
