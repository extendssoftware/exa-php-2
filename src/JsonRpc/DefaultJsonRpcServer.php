<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\JsonRpc;

use ExtendsSoftware\ExaPHP\JsonRpc\Message\ErrorResponse;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\MessageDecoder;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\MessageEncoder;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\Notification;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\Request;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\ResultResponse;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\RegisteredMethodDispatcher;
use Override;
use SensitiveParameter;

use function is_array;

/**
 * Coordinates JSON-RPC decoding, method dispatch, and response encoding.
 *
 * Batch entries execute in input order. Each response is encoded before the next entry executes, without retrying
 * serializers when another response fails. Notifications execute without producing responses.
 */
final readonly class DefaultJsonRpcServer implements JsonRpcServer
{
    /**
     * Creates a server with explicit message processing collaborators.
     *
     * @param MessageDecoder $decoder The JSON document decoder.
     * @param RegisteredMethodDispatcher $dispatcher The registered method dispatcher.
     * @param MessageEncoder $encoder The response encoder.
     */
    public function __construct(
        private MessageDecoder $decoder,
        private RegisteredMethodDispatcher $dispatcher,
        private MessageEncoder $encoder,
    ) {}

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function handle(#[SensitiveParameter] string $message): ?string
    {
        $decoded = $this->decoder->decode($message);
        if (is_array($decoded)) {
            return $this->encoder->encodeBatch($this->dispatchBatch($decoded));
        }
        $response = $decoded instanceof ErrorResponse ? $decoded : $this->dispatcher->dispatch($decoded);

        return $response === null ? null : $this->encoder->encode($response);
    }

    /**
     * Dispatches batch entries lazily so each response can be encoded before executing the next entry.
     *
     * @param list<ErrorResponse|Notification|Request> $messages The decoded batch entries.
     *
     * @return iterable<ErrorResponse|ResultResponse> Request and invalid-entry responses, excluding notifications.
     */
    private function dispatchBatch(#[SensitiveParameter] array $messages): iterable
    {
        foreach ($messages as $message) {
            $response = $message instanceof ErrorResponse ? $message : $this->dispatcher->dispatch($message);
            if ($response !== null) {
                yield $response;
            }
        }
    }
}
