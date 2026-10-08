<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Authentication\Bearer;

use ExtendsSoftware\ExaPHP\Authentication\Credentials;
use ExtendsSoftware\ExaPHP\Authentication\Exception\InvalidCredentialsException;
use SensitiveParameter;
use SensitiveParameterValue;

use function preg_match;

/**
 * Holds a syntactically valid Bearer token without verifying its authenticity.
 */
final readonly class BearerTokenCredentials implements Credentials
{
    /**
     * Wraps the token to prevent accidental debug disclosure and serialization.
     *
     * @var SensitiveParameterValue
     */
    private SensitiveParameterValue $token;

    /**
     * Creates credentials preserving the token exactly.
     *
     * @param non-empty-string $token The sensitive token using Bearer token syntax.
     *
     * @throws InvalidCredentialsException When the token syntax is invalid.
     */
    public function __construct(#[SensitiveParameter] string $token)
    {
        if (preg_match('/\A[A-Za-z0-9._~+\/-]+=*\z/', $token) !== 1) {
            throw new InvalidCredentialsException('Invalid Bearer token syntax.');
        }
        $this->token = new SensitiveParameterValue($token);
    }

    /**
     * Returns the sensitive token for verification.
     *
     * @return non-empty-string The original token, which must not be logged.
     */
    public function token(): string
    {
        return $this->token->getValue();
    }
}
