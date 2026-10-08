<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Authentication;

use ExtendsSoftware\ExaPHP\Authentication\Exception\AuthenticationFailedException;
use ExtendsSoftware\ExaPHP\Authentication\Exception\InvalidCredentialsException;
use ExtendsSoftware\ExaPHP\Authentication\Exception\UnsupportedCredentialsException;
use ExtendsSoftware\ExaPHP\Identity\Actor;

/**
 * Verifies credentials and establishes the authenticated principal.
 */
interface Authenticator
{
    /**
     * Returns an actor only after successful credential verification.
     *
     * Rejected credentials and unsupported credential types must be distinguished from operational failures.
     * Expected verification infrastructure failures must be translated to AuthenticationFailedException with the
     * original cause preserved. Authentication does not grant permissions or create a session.
     *
     * @param Credentials $credentials The credentials to verify, treated as sensitive data.
     *
     * @return Actor The principal established by successful verification.
     *
     * @throws InvalidCredentialsException When the credentials are rejected.
     * @throws UnsupportedCredentialsException When the credential type is not supported.
     * @throws AuthenticationFailedException When an operational failure prevents verification.
     */
    public function authenticate(Credentials $credentials): Actor;
}
