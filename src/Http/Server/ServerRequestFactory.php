<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Server;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use ExtendsSoftware\ExaPHP\Http\Message\Request;

/**
 * Creates the incoming request at the server boundary.
 */
interface ServerRequestFactory
{
    /**
     * Creates a request for the current server invocation.
     *
     * @return Request The incoming request.
     *
     * @throws HttpException When request creation fails.
     */
    public function create(): Request;
}
