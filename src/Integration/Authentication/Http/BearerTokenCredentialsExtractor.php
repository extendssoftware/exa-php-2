<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Authentication\Http;

use ExtendsSoftware\ExaPHP\Authentication\Bearer\BearerTokenCredentials;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Integration\Authentication\Http\Exception\MalformedCredentialsException;
use Override;
use SensitiveParameter;

use function count;
use function preg_match;
use function strcasecmp;

/**
 * Extracts Bearer tokens exclusively from a single Authorization header.
 */
final readonly class BearerTokenCredentialsExtractor implements RequestCredentialsExtractor
{
    /**
     * Parses a case-insensitive Bearer scheme followed by spaces and a Bearer token.
     *
     * Multiple header values and malformed input are rejected. Other schemes return null. Tokens are not decoded;
     * query parameters and request bodies are not credential sources.
     *
     * @param Request $request The request containing sensitive authentication data.
     *
     * @return BearerTokenCredentials|null The token credentials, or null for absent or unsupported authentication.
     *
     * @throws MalformedCredentialsException When the header is malformed or repeated.
     */
    #[Override]
    public function extract(#[SensitiveParameter] Request $request): ?BearerTokenCredentials
    {
        $values = $request->headers->get('Authorization');
        if ($values === []) {
            return null;
        }
        if (count($values) !== 1
            || preg_match('/\A([!#$%&\x27*+.^_`|~0-9A-Za-z-]+)(?:[ \t]|\z)/', $values[0], $scheme) !== 1) {
            throw new MalformedCredentialsException('Malformed Authorization header.');
        }
        if (strcasecmp($scheme[1], 'Bearer') !== 0) {
            return null;
        }
        if (preg_match('/\ABearer +([A-Za-z0-9._~+\/-]+=*)\z/i', $values[0], $matches) !== 1) {
            throw new MalformedCredentialsException('Malformed Bearer credentials.');
        }

        return new BearerTokenCredentials($matches[1]);
    }
}
