<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Message;

use ExtendsSoftware\ExaPHP\Http\Message\Body\Body;
use ExtendsSoftware\ExaPHP\Http\Message\Body\StringBody;

/**
 * Carries immutable HTTP response metadata and an explicitly supplied body.
 */
final readonly class Response
{
    /**
     * Creates a response without emitting it or inferring content headers.
     *
     * @param StatusCode $statusCode The supported HTTP response status.
     * @param Headers $headers The explicit response headers.
     * @param Body $body The body, shared without cloning or transferring resource ownership.
     * @param ProtocolVersion $protocolVersion The HTTP version.
     */
    public function __construct(
        public StatusCode $statusCode = StatusCode::Ok,
        public Headers $headers = new Headers(),
        public Body $body = new StringBody(),
        public ProtocolVersion $protocolVersion = ProtocolVersion::Http11,
    ) {
    }

    /**
     * Returns a response with a replacement status code.
     *
     * @param StatusCode $statusCode The replacement status.
     *
     * @return self A new response retaining other values and shared body identity.
     */
    public function withStatusCode(StatusCode $statusCode): self
    {
        return new self($statusCode, $this->headers, $this->body, $this->protocolVersion);
    }

    /**
     * Returns a response with a replacement header collection.
     *
     * @param Headers $headers The replacement value.
     *
     * @return self A new response retaining other values and shared body identity.
     */
    public function withHeaders(Headers $headers): self
    {
        return new self($this->statusCode, $headers, $this->body, $this->protocolVersion);
    }

    /**
     * Returns a response with a replacement body.
     *
     * @param Body $body The replacement value.
     *
     * @return self A new response retaining other values and shared body identity.
     */
    public function withBody(Body $body): self
    {
        return new self($this->statusCode, $this->headers, $body, $this->protocolVersion);
    }

    /**
     * Returns a response with a replacement protocol version.
     *
     * @param ProtocolVersion $protocolVersion The replacement value.
     *
     * @return self A new response retaining other values and shared body identity.
     */
    public function withProtocolVersion(ProtocolVersion $protocolVersion): self
    {
        return new self($this->statusCode, $this->headers, $this->body, $protocolVersion);
    }
}
