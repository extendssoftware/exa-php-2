<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Message;

use ExtendsSoftware\ExaPHP\Http\Message\Exception\InvalidUriException;
use Uri\InvalidUriException as NativeInvalidUriException;
use Uri\Rfc3986\Uri as NativeUri;

/**
 * Holds a validated RFC 3986 URI reference while preserving its supplied representation.
 */
final readonly class Uri
{
    /**
     * Parsed URI components, read through raw accessors to avoid normalization.
     *
     * @var NativeUri
     */
    private NativeUri $parsed;

    /**
     * Creates a URI reference without decoding or normalizing its path.
     *
     * @param string $value The URI reference; relative and absolute references are accepted.
     *
     * @throws InvalidUriException When the reference is malformed.
     */
    public function __construct(private string $value)
    {
        try {
            $this->parsed = new NativeUri($value);
        } catch (NativeInvalidUriException $exception) {
            throw new InvalidUriException('The URI reference is invalid.', 0, $exception);
        }
    }
    /**
     * Returns the scheme with its original spelling.
     *
     * @return string|null The scheme, or null when absent.
     */
    public function scheme(): ?string
    {
        return $this->parsed->getRawScheme();
    }

    /**
     * Returns encoded user information.
     *
     * @return string|null User information, or null when absent.
     */
    public function userInfo(): ?string
    {
        return $this->parsed->getRawUserInfo();
    }

    /**
     * Returns the host with its original spelling and IPv6 brackets.
     *
     * @return string|null The host, or null when no authority is present.
     */
    public function host(): ?string
    {
        return $this->parsed->getRawHost();
    }

    /**
     * Returns the explicitly specified port.
     *
     * @return int|null The port, or null when absent.
     */
    public function port(): ?int
    {
        return $this->parsed->getPort();
    }

    /**
     * Returns the encoded path without changing slashes or dot segments.
     *
     * @return string The raw path, possibly empty.
     */
    public function path(): string
    {
        return $this->parsed->getRawPath();
    }

    /**
     * Returns the encoded query without parsing or reordering parameters.
     *
     * @return string|null The query, empty when explicitly empty, or null when absent.
     */
    public function query(): ?string
    {
        return $this->parsed->getRawQuery();
    }

    /**
     * Returns the encoded fragment.
     *
     * @return string|null The fragment, empty when explicitly empty, or null when absent.
     */
    public function fragment(): ?string
    {
        return $this->parsed->getRawFragment();
    }

    /**
     * Returns the exact URI reference supplied at construction.
     *
     * @return string The original URI representation.
     */
    public function toString(): string
    {
        return $this->value;
    }
}
