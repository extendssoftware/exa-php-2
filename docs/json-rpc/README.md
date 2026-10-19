# JSON-RPC

The `ExtendsSoftware\ExaPHP\JsonRpc` namespace provides a transport-independent
[JSON-RPC 2.0](https://www.jsonrpc.org/specification) server. `JsonRpcException` is the component's root exception
contract.

## Register and invoke a method

Register method names against `Method\MethodHandler` instances using `RegisteredMethodDispatcher`.
Construct `DefaultJsonRpcServer` with a `MessageDecoder`, the dispatcher, and a `MessageEncoder`.
`CallableMethodHandler` adapts application callables:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\JsonRpc\DefaultJsonRpcServer;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\MessageDecoder;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\MessageEncoder;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\CallableMethodHandler;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\RegisteredMethodDispatcher;

require 'vendor/autoload.php';

$dispatcher = new RegisteredMethodDispatcher([
    'echo' => new CallableMethodHandler(static fn(array|stdClass $params): array|stdClass => $params),
]);
$encoder = new MessageEncoder();
$server = new DefaultJsonRpcServer(new MessageDecoder(), $dispatcher, $encoder);

$response = $server->handle('{"jsonrpc":"2.0","method":"echo","params":{"name":"Ada"},"id":1}');
if ($response !== null) {
    echo $response;
}
```

The result is `{"jsonrpc":"2.0","result":{"name":"Ada"},"id":1}`. Handlers may return any JSON-encodable value,
including null, scalars, arrays, and objects. Objects follow PHP's JSON serialization rules.

Registrations are fixed after construction; handlers are shared between calls. Names are case-sensitive. Numeric
method names are supported even when PHP converts their registration keys to integers. The `rpc.` prefix is reserved
for JSON-RPC extensions and cannot be registered. Invalid handler values or reserved names raise
`Method\Exception\InvalidMethodRegistrationException`, implementing `JsonRpcException`.

## Register the application module

Register `Integration\JsonRpc\JsonRpcModule` before application bootstrap to obtain shared JSON-RPC services. The
application configuration directory must exist. Place method registrations in an application `config/*.global.php`
file or contribute them from a business module:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\JsonRpc\Method\CallableMethodHandler;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;

return [
    'jsonRpc' => [
        'methods' => ['echo' => 'app.jsonRpc.echo'],
    ],
    'services' => [
        'app.jsonRpc.echo' => new FactoryDefinition(
            static fn(): CallableMethodHandler => new CallableMethodHandler(
                static fn(array|stdClass $params): mixed => $params,
            ),
        ),
    ],
];
```

Bootstrap the application and resolve `JsonRpcServer`:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Integration\JsonRpc\JsonRpcModule;
use ExtendsSoftware\ExaPHP\JsonRpc\JsonRpcServer;

require 'vendor/autoload.php';

$application = new Application(__DIR__ . '/config');
$application->registerModule(JsonRpcModule::class);
$services = $application->bootstrap();

try {
    $server = $services->get(JsonRpcServer::class);
    $response = $server->handle('{"jsonrpc":"2.0","method":"echo","params":["hello"],"id":1}');
    if ($response !== null) {
        echo $response;
    }
} finally {
    $application->shutdown();
}
```

The module registers these shared services:

- `JsonRpcServer`: a `DefaultJsonRpcServer` composed by `Integration\JsonRpc\Factory\JsonRpcServerFactory`.
- `Message\MessageDecoder`: the JSON document decoder.
- `Message\MessageEncoder`: the response encoder.
- `Method\RegisteredMethodDispatcher`: a dispatcher composed by `Integration\JsonRpc\Factory\MethodDispatcherFactory`.

Service resolution is lazy. When the dispatcher is first resolved, all configured method handler services are resolved
in configuration order without executing their methods. Resolving the server also resolves the dispatcher. Missing
`jsonRpc` or `jsonRpc.methods` configuration produces an empty dispatcher. Present sections must be arrays, and handler
service identifiers must be non-empty strings. Method names are literal array keys, including names containing dots,
numeric names, and the empty name supported by the core.

Method maps follow the application [configuration merge rules](../application/configuration.md): modules can contribute
distinct names, and a later entry for the same name replaces its handler service identifier. Override the
`JsonRpcServer` service in application configuration to provide a different implementation, or supply a preconfigured
`RegisteredMethodDispatcher` service. The module can be used independently and its server injected into an application
transport adapter.

Malformed configuration and incompatible decoder, dispatcher, or encoder services raise
`Integration\JsonRpc\Exception\InvalidJsonRpcConfigurationException`, implementing `IntegrationException`.
Direct factory calls propagate service-resolution and JSON-RPC registration exceptions unchanged. Resolution through
`FactoryDefinition` preserves service-locator exceptions and wraps other factory failures in
`ServiceResolutionException` with the original failure as its previous exception.

## Validate method arguments

Handlers receive the entire parameter structure: a PHP list for positional JSON arrays, or `stdClass` for named JSON
objects. Omitted parameters become an empty positional list. A handler validates the arguments its method requires;
the server validates the envelope and parameter structure.

Implement `MethodHandler` directly when the operation needs injected collaborators, or use `CallableMethodHandler`
for a callable. Both requests and notifications execute through the same handler contract. Argument validation and
execution failures are translated by `RegisteredMethodDispatcher`; JSON serialization belongs to `MessageEncoder`.

Throw `Method\Exception\MethodCallException` for an intentional public error. For example, a handler requiring a named
string argument can begin with:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\JsonRpc\ErrorCode;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\Exception\MethodCallException;

function greet(array|stdClass $params): string
{
    if (!$params instanceof stdClass || !is_string($params->name ?? null)) {
        throw new MethodCallException(ErrorCode::InvalidParams->value, 'A string name is required.');
    }

    return 'Hello, ' . $params->name;
}
```

Register this function through `new CallableMethodHandler(greet(...))`. Application-defined error codes are also
accepted; choose codes outside the reserved JSON-RPC range. An optional `data` argument provides public error details;
null omits that member. An optional previous exception preserves an internal cause without exposing it in the response.

## Process typed messages directly

Use the message classes when invoking or inspecting JSON-RPC operations without parsing or encoding JSON:

| Type in `JsonRpc\Message` | Purpose |
| --- | --- |
| `ErrorDetails` | Integer error code, public message, and optional data. |
| `ErrorResponse` | Request identifier and error details. |
| `Notification` | Method name and parameters, without an identifier. |
| `Request` | Explicit identifier, method name, and parameters. |
| `ResultResponse` | Request identifier and application result. |

Envelope fields are readonly. Nested argument, result, and error-data objects retain their mutability and serialization
behavior. Constructors reject non-finite identifiers, invalid UTF-8 identifiers or method names, and associative PHP
arrays used as positional parameters. These failures use `Message\Exception\InvalidMessageException`, implementing
`JsonRpcException`. Use `stdClass` for named parameters.

Using `$dispatcher` and `$encoder` from the first example:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\JsonRpc\Message\Request;

$request = new Request(id: null, method: 'echo', params: (object) ['name' => 'Ada']);
$response = $dispatcher->dispatch($request);
if ($response !== null) {
    echo $encoder->encode($response);
}
```

`MessageDecoder::decode()` returns a `Request`, `Notification`, `ErrorResponse`, or a list of those values for a batch.
Omitted parameters are null in message objects; the dispatcher supplies an empty positional list to handlers. Explicit
JSON `params: null` is invalid. The decoder validates envelopes without validating method-specific arguments or
executing handlers. Malformed input produces error responses instead of constructor exceptions.

`RegisteredMethodDispatcher::dispatch()` returns a `ResultResponse`, `ErrorResponse`, or null for a notification. It
does not serialize results. `MessageEncoder::encode()` converts a typed response to JSON; `encodeBatch()` accepts an
iterable of responses and returns a JSON array or null for an empty source. It consumes the iterable once and encodes
each response before requesting the next item. Exceptions from a supplied iterable propagate unchanged.

## Execute notifications and batches

A message without `id` is a notification. Its handler executes, but `handle()` returns null rather than an encoded
response. Expected errors and unexpected failures also produce no notification response. An explicit `"id": null`
is a request and receives a response with a null identifier.

Pass a JSON array to execute a batch. Entries execute synchronously in input order. The response is a JSON array
containing responses for requests and invalid entries, excluding notifications. A batch with one request still returns
an array; an empty batch produces a single Invalid Request error object. An all-notification batch returns null.
Nested batches are invalid entries. A failure in one entry does not prevent subsequent entries from executing.
Each response is encoded before the next batch entry executes, and the encoded responses form the response array.

Batch execution is not atomic: successful operations are not rolled back when another entry fails. Treat transaction
management as part of the application operation.

## Handle protocol failures

The server uses the standard `ErrorCode` values:

| Error | Code | Meaning |
| --- | --- | --- |
| Parse error | `-32700` | The input cannot be decoded as JSON. |
| Invalid Request | `-32600` | The envelope, identifier, or parameter structure is invalid. |
| Method not found | `-32601` | No handler is registered for the method. |
| Invalid params | `-32602` | A handler explicitly reports invalid method arguments. |
| Internal error | `-32603` | Unexpected execution or response serialization failed. |

Use `ErrorCode::InvalidParams->response(id: 1, data: ['field' => 'name', 'reason' => 'required'])` to build a typed
`ErrorResponse` with public details. Pass it to `MessageEncoder::encode()` for its wire representation. The
`MethodCallException::response()` factory also returns an `ErrorResponse`. `data` accepts any JSON-encodable value
and is omitted from encoded errors when null.

Every response includes `id`, using null for parse errors and invalid request objects. Recognized requests retain their
identifier on results and execution errors. Parameter structures must be JSON objects or arrays; explicit null and
scalar parameters are invalid request objects, even without an identifier.

`MethodCallException` exposes its explicitly supplied code, message, and data. Other exceptions and engine errors from
handlers become generic internal errors. Response encoding failures, including serializer exceptions and engine errors,
also become internal errors for the affected request. Exception messages and traces are not copied into these errors.
Notification results are discarded without serialization.

Each response is encoded once. If serialization fails, an Internal Error response is encoded in its place without
retrying the original response or re-encoding successful responses from other batch entries.

## Connect a transport

Inject `JsonRpcServer` into an adapter, pass the incoming JSON document to `handle()`, and send a response only when
the result is non-null. The component handles JSON decoding and encoding. Framing, HTTP status codes, stream handling,
and caller authentication belong to the adapter or application.

Decoding uses PHP's default JSON depth limit of 512. Objects remain distinct from arrays, and numbers follow PHP's
numeric precision. Use string identifiers when exact values exceed that precision. Null and finite numeric identifiers
are accepted; fractional identifiers are supported but discouraged by JSON-RPC.
