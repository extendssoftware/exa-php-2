<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Query;

use ExtendsSoftware\ExaPHP\Cqrs\CqrsException;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;
use Throwable;

/**
 * Answers queries without changing application state.
 */
interface QueryBus
{
    /**
     * Returns the result of query execution.
     *
     * Dispatch failures use the CQRS exception contract. Unhandled execution failures propagate unchanged.
     *
     * @template TResult
     *
     * @param Query<TResult> $query The query to answer.
     * @param DispatchContext $context The application-defined execution metadata.
     *
     * @return TResult The query result.
     *
     * @throws CqrsException When the query cannot be dispatched.
     * @throws Throwable When execution throws an unhandled exception or error, propagated unchanged.
     */
    public function ask(Query $query, DispatchContext $context = new DispatchContext()): mixed;
}
