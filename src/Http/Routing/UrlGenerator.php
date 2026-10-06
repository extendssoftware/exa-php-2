<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;

/**
 * Generates a URI reference from a named route and explicit values.
 */
interface UrlGenerator
{
    /**
     * Generates a path and optional query without inferring an origin from a request.
     *
     * Parameters are raw values encoded as individual path segments. Missing and unexpected parameters are rejected.
     * Query keys are nonempty strings; null values are omitted and booleans become 1 or 0.
     *
     * @param string $routeName The exact route name.
     * @param array<string, string|int> $parameters The raw path parameter values.
     * @param array<string, string|int|bool|null> $query The query values.
     *
     * @return Uri The generated URI reference without scheme or authority.
     *
     * @throws HttpException When the route is unknown or cannot be generated with the supplied values.
     */
    public function generate(string $routeName, array $parameters = [], array $query = []): Uri;
}
