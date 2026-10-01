<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Query\Middleware;

use ExtendsSoftware\ExaPHP\Cqrs\Query\Query;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;
use Throwable;

/**
 * Executes the remaining query pipeline.
 */
interface QueryExecution
{
    /**
     * Processes a query with explicit execution metadata.
     *
     * @template TResult
     *
     * @param Query<TResult> $query The query to execute.
     * @param DispatchContext $context The execution metadata.
     *
     * @return TResult The query result.
     *
     * @throws Throwable When execution fails.
     */
    public function execute(Query $query, DispatchContext $context): mixed;
}
