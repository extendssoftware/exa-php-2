<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Query\Middleware;

use ExtendsSoftware\ExaPHP\Cqrs\Query\Query;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;
use Throwable;

/**
 * Wraps query execution with application behavior.
 */
interface QueryMiddleware
{
    /**
     * Processes a query with explicit execution metadata.
     *
     * @template TResult
     *
     * @param Query<TResult> $query The query to execute.
     * @param DispatchContext $context The execution metadata.
     * @param QueryExecution $next The remaining pipeline; return a result directly to short-circuit.
     *
     * @return TResult The query result.
     *
     * @throws Throwable When execution fails.
     */
    public function process(Query $query, DispatchContext $context, QueryExecution $next): mixed;
}
