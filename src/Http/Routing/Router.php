<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use ExtendsSoftware\ExaPHP\Http\Method;
use ExtendsSoftware\ExaPHP\Http\Request;

/**
 * Selects request routes and exposes the methods available for a request target.
 */
interface Router
{
    /**
     * Selects a route without invoking its handler or modifying the request.
     *
     * @param Request $request The request to match.
     *
     * @return RouteMatch|null The selected route and parameters, or null when no route accepts the request.
     *
     * @throws HttpException When route selection cannot complete.
     */
    public function match(Request $request): ?RouteMatch;

    /**
     * Returns methods available for the target independent of the request's current method.
     *
     * The result must agree with match() and contain each method at most once.
     *
     * @param Request $request The request whose target is being inspected.
     *
     * @return list<Method> Allowed methods, or an empty list when the target is not routed.
     *
     * @throws HttpException When method inspection cannot complete.
     */
    public function allowedMethods(Request $request): array;
}
