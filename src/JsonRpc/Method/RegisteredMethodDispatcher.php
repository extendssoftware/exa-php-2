<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\JsonRpc\Method;

use ExtendsSoftware\ExaPHP\JsonRpc\ErrorCode;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\ErrorResponse;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\Notification;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\Request;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\ResultResponse;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\Exception\InvalidMethodRegistrationException;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\Exception\MethodCallException;
use SensitiveParameter;
use Throwable;

use function str_starts_with;

/**
 * Executes validated messages through fixed, case-sensitive method registrations.
 *
 * Handler instances are shared. Public method errors become error responses; unexpected exceptions and engine errors
 * become generic internal errors. Notifications execute through the same handlers without producing responses.
 */
final readonly class RegisteredMethodDispatcher
{
    /**
     * Method handlers detached from caller-owned registration array references.
     *
     * @var array<array-key, MethodHandler>
     */
    private array $methods;

    /**
     * Registers method names and their shared handlers.
     *
     * Integer keys represent numeric method names because PHP converts integer-like string array keys to integers.
     * The `rpc.` prefix is reserved for JSON-RPC extensions and cannot be registered here.
     *
     * @param array<array-key, mixed> $methods Method handlers indexed by method name.
     *
     * @throws InvalidMethodRegistrationException When a name is reserved or a handler is invalid.
     */
    public function __construct(array $methods = [])
    {
        $registered = [];
        foreach ($methods as $name => $handler) {
            if (str_starts_with((string) $name, 'rpc.') || !$handler instanceof MethodHandler) {
                throw new InvalidMethodRegistrationException(
                    'Methods require non-reserved names and MethodHandler values.',
                );
            }
            $registered[$name] = $handler;
        }
        $this->methods = $registered;
    }

    /**
     * Executes a request or notification without decoding or encoding JSON.
     *
     * Omitted parameters are delivered as an empty positional list. Unknown request methods produce Method Not Found
     * responses; unknown notification methods produce no response. Results are not serialized or validated here.
     *
     * @param Notification|Request $message The validated message to execute.
     *
     * @return ErrorResponse|ResultResponse|null The result or error response, or null for a notification.
     */
    public function dispatch(#[SensitiveParameter] Notification|Request $message): ErrorResponse|ResultResponse|null
    {
        $handler = $this->methods[$message->method] ?? null;
        if ($handler === null) {
            return $message instanceof Notification ? null : ErrorCode::MethodNotFound->response($message->id);
        }
        try {
            $result = $handler->handle($message->params ?? []);
        } catch (MethodCallException $exception) {
            return $message instanceof Notification ? null : $exception->response($message->id);
        } catch (Throwable) {
            return $message instanceof Notification ? null : ErrorCode::InternalError->response($message->id);
        }

        return $message instanceof Notification ? null : new ResultResponse($message->id, $result);
    }
}
