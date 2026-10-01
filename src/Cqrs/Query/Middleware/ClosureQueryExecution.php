<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Query\Middleware;

use Closure;
use ExtendsSoftware\ExaPHP\Cqrs\Query\Query;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;
use Throwable;

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
     * Executes the step with the supplied query and context.
     *
     * @template TResult
     *
     * @param Query<TResult> $query The query to execute.
     * @param DispatchContext $context The execution metadata.
     *
     * @return TResult The query result.
     *
     * @throws Throwable When the step fails, propagated unchanged.
     */
    public function execute(Query $query, DispatchContext $context): mixed
    {
        return ($this->execution)($query, $context);
    }
}
