<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Authentication\Http;

use ExtendsSoftware\ExaPHP\Authentication\Credentials;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Integration\Authentication\Http\Exception\MalformedCredentialsException;

/**
 * Extracts credentials for a supported HTTP authentication scheme.
 */
interface RequestCredentialsExtractor
{
    /**
     * Extracts credentials without verifying them.
     *
     * @param Request $request The request containing sensitive authentication data.
     *
     * @return Credentials|null Credentials, or null when absent or using an unsupported scheme.
     *
     * @throws MalformedCredentialsException When authentication input is malformed or ambiguous.
     */
    public function extract(Request $request): ?Credentials;
}
