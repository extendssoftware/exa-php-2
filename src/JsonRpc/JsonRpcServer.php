<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\JsonRpc;

use SensitiveParameter;

/**
 * Processes JSON-RPC 2.0 requests and notifications independently of their transport.
 */
interface JsonRpcServer
{
    /**
     * Processes a JSON document and returns its encoded response, when required.
     *
     * Requests produce result or error responses with their identifiers. Notifications execute without responses.
     * Batches produce a response array excluding notifications; a batch containing only notifications returns null.
     * Malformed JSON and invalid request objects produce the corresponding JSON-RPC errors with a null identifier.
     *
     * @param string $message The JSON document, which may contain sensitive method arguments.
     *
     * @return string|null The encoded JSON-RPC response, or null when no response is required.
     *
     * @throws JsonRpcException When processing cannot produce the required response.
     */
    public function handle(#[SensitiveParameter] string $message): ?string;
}
