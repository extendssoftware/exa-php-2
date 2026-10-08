<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Authentication\Http;

/**
 * Identifies client authentication failures handled at the HTTP boundary.
 */
enum AuthenticationFailure
{
    /**
     * Credentials are absent or use another scheme.
     */
    case MissingCredentials;

    /**
     * Authentication input is malformed or ambiguous.
     */
    case MalformedCredentials;

    /**
     * Credential verification rejected the credentials.
     */
    case RejectedCredentials;
}
