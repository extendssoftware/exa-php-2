<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Authentication\Http;

use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use Throwable;

/**
 * Presents client authentication failures with the configured scheme's challenge.
 */
interface AuthenticationResponseFactory
{
    /**
     * Creates a safe authentication failure response.
     *
     * @param AuthenticationFailure $failure The client failure category.
     * @param Request $request The request at the authentication boundary.
     *
     * @return Response The failure response including the authentication challenge.
     *
     * @throws Throwable When response creation fails.
     */
    public function create(AuthenticationFailure $failure, Request $request): Response;
}
