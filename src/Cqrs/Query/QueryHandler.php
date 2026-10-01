<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Query;

use Throwable;

/**
 * Answers a query without changing application state.
 *
 * @template TQuery of Query<TResult>
 * @template TResult
 */
interface QueryHandler
{
    /**
     * Returns the result requested by the query without changing application state.
     *
     * @param TQuery $query The query to handle.
     *
     * @return TResult The query result.
     *
     * @throws Throwable When query handling raises a domain exception or another exception or error.
     */
    public function handle(Query $query): mixed;
}
