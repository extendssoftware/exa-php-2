<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Query;

use ExtendsSoftware\ExaPHP\Cqrs\CqrsException;
use Throwable;

/**
 * Answers queries without changing application state.
 */
interface QueryBus
{
    /**
     * Returns the result produced by the query's handler.
     *
     * Dispatch failures use the CQRS exception contract. Handler exceptions and errors propagate unchanged.
     *
     * @template TResult
     *
     * @param Query<TResult> $query The query to answer.
     *
     * @return TResult The query result.
     *
     * @throws CqrsException When the query cannot be dispatched.
     * @throws Throwable When the handler throws an exception or error, propagated unchanged.
     */
    public function ask(Query $query): mixed;
}
