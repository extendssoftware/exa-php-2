<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http;

use ExtendsSoftware\ExaPHP\Http\Body\Body;
use ExtendsSoftware\ExaPHP\Http\Body\StringBody;
use ExtendsSoftware\ExaPHP\Http\Exception\InvalidRequestException;

use function in_array;
use function str_starts_with;
use function strtolower;

/**
 * Carries immutable HTTP request metadata and a body whose consumption semantics belong to its implementation.
 */
final readonly class Request
{
    /**
     * Creates a request without reading the body or modifying headers.
     *
     * @param Method $method The supported request method.
     * @param Uri $uri An absolute HTTP(S), network-path, or origin-path URI; OPTIONS also accepts an asterisk.
     * @param Headers $headers The explicit headers; Host is not inferred from the URI.
     * @param Body $body The body, shared without cloning or transferring resource ownership.
     * @param ProtocolVersion $protocolVersion The HTTP version.
     * @param RequestAttributes $attributes Typed metadata; stored objects are shared without cloning.
     *
     * @throws InvalidRequestException When the URI cannot represent a supported request target.
     */
    public function __construct(
        public Method $method,
        public Uri $uri,
        public Headers $headers = new Headers(),
        public Body $body = new StringBody(),
        public ProtocolVersion $protocolVersion = ProtocolVersion::Http11,
        public RequestAttributes $attributes = new RequestAttributes(),
    ) {
        if ($uri->fragment() !== null || $uri->userInfo() !== null) {
            throw new InvalidRequestException('Request URIs must not contain fragments or user information.');
        }
        $scheme = $uri->scheme();
        $host = $uri->host();
        if ($scheme !== null
            && (!in_array(strtolower($scheme), ['http', 'https'], true) || $host === null || $host === '')) {
            throw new InvalidRequestException('Absolute request URIs require HTTP(S) and a non-empty host.');
        }
        if ($uri->port() !== null && ($uri->port() < 0 || $uri->port() > 65535)) {
            throw new InvalidRequestException('Request URI ports must be between 0 and 65535.');
        }
        if ($host === '') {
            throw new InvalidRequestException('Request URI authorities require a non-empty host.');
        }
        if ($method === Method::Connect) {
            if ($host === null || $uri->port() === null || $uri->path() !== '' || $uri->query() !== null) {
                throw new InvalidRequestException('CONNECT requires a host and explicit port without a path or query.');
            }
        } elseif ($uri->path() === '*') {
            if ($method !== Method::Options || $host !== null || $scheme !== null || $uri->query() !== null) {
                throw new InvalidRequestException('Only OPTIONS supports the bare asterisk target.');
            }
        } elseif ($uri->path() !== '' && !str_starts_with($uri->path(), '/')) {
            throw new InvalidRequestException('Request URI paths must be empty or start with a slash.');
        }
    }

    /**
     * Returns the origin-form target, authority for CONNECT, or asterisk for OPTIONS.
     *
     * @return string The encoded target, retaining query syntax and trailing slashes.
     */
    public function target(): string
    {
        if ($this->method === Method::Connect) {
            return $this->uri->host() . ':' . $this->uri->port();
        }
        $path = $this->uri->path() === '' ? '/' : $this->uri->path();
        $query = $this->uri->query();

        return $query === null ? $path : $path . '?' . $query;
    }

    /**
     * Returns a request with replacement method.
     *
     * @param Method $method The replacement value.
     *
     * @return self A new request retaining other values and shared body identity.
     *
     * @throws InvalidRequestException When the resulting request target is invalid.
     */
    public function withMethod(Method $method): self
    {
        return new self($method, $this->uri, $this->headers, $this->body, $this->protocolVersion, $this->attributes);
    }

    /**
     * Returns a request with replacement URI.
     *
     * @param Uri $uri The replacement value.
     *
     * @return self A new request retaining other values and shared body identity.
     *
     * @throws InvalidRequestException When the resulting request target is invalid.
     */
    public function withUri(Uri $uri): self
    {
        return new self($this->method, $uri, $this->headers, $this->body, $this->protocolVersion, $this->attributes);
    }

    /**
     * Returns a request with replacement headers.
     *
     * @param Headers $headers The replacement value.
     *
     * @return self A new request retaining other values and shared body identity.
     *
     * @throws InvalidRequestException When the resulting request target is invalid.
     */
    public function withHeaders(Headers $headers): self
    {
        return new self($this->method, $this->uri, $headers, $this->body, $this->protocolVersion, $this->attributes);
    }

    /**
     * Returns a request with replacement body.
     *
     * @param Body $body The replacement value.
     *
     * @return self A new request retaining other values and shared body identity.
     *
     * @throws InvalidRequestException When the resulting request target is invalid.
     */
    public function withBody(Body $body): self
    {
        return new self($this->method, $this->uri, $this->headers, $body, $this->protocolVersion, $this->attributes);
    }

    /**
     * Returns a request with replacement protocol version.
     *
     * @param ProtocolVersion $protocolVersion The replacement value.
     *
     * @return self A new request retaining other values and shared body identity.
     *
     * @throws InvalidRequestException When the resulting request target is invalid.
     */
    public function withProtocolVersion(ProtocolVersion $protocolVersion): self
    {
        return new self($this->method, $this->uri, $this->headers, $this->body, $protocolVersion, $this->attributes);
    }

    /**
     * Returns a request with a replacement attribute collection.
     *
     * @param RequestAttributes $attributes The replacement metadata collection.
     *
     * @return self A new request sharing all other values.
     */
    public function withAttributes(RequestAttributes $attributes): self
    {
        return new self($this->method, $this->uri, $this->headers, $this->body, $this->protocolVersion, $attributes);
    }

    /**
     * Returns a request with metadata added or replaced by exact concrete class.
     *
     * @param object $attribute The metadata object, which should be immutable.
     *
     * @return self A new request with the updated collection.
     */
    public function withAttribute(object $attribute): self
    {
        return $this->withAttributes($this->attributes->with($attribute));
    }
}
