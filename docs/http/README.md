# HTTP

The `ExtendsSoftware\ExaPHP\Http` component provides immutable request and response metadata, URI and header values,
and body, handler, and middleware contracts. It has no PSR dependency. URI parsing requires PHP 8.5's `ext-uri` extension,
which Composer checks during installation.

## Create requests and responses

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Http\Body\StringBody;
use ExtendsSoftware\ExaPHP\Http\Headers;
use ExtendsSoftware\ExaPHP\Http\Method;
use ExtendsSoftware\ExaPHP\Http\Request;
use ExtendsSoftware\ExaPHP\Http\Response;
use ExtendsSoftware\ExaPHP\Http\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Uri;

$request = new Request(
    Method::Post,
    new Uri('/articles'),
    new Headers(['Content-Type' => 'application/json']),
    new StringBody('{"title":"Article"}'),
);

$response = new Response(
    StatusCode::Created,
    new Headers(['Content-Type' => 'application/json', 'Location' => '/articles/1']),
    new StringBody('{"id":1}'),
);
```

`Request`, `Response`, `Headers`, and `Uri` are final readonly classes. Request and response properties are public and
read-only. Their `with*()` methods create new messages, preserving the other values and the body object's identity.

```php
$updated = $response->withHeaders($response->headers->with('Cache-Control', 'no-store'));
// $response retains its original headers.
```

Messages default to empty headers, an empty `StringBody`, and `ProtocolVersion::Http11`. `Response` defaults to status
200. Supported method enum cases are `Get`, `Head`, `Post`, `Put`, `Delete`, `Connect`, `Options`, `Trace`, and `Patch`.
Their backed values are the uppercase method tokens. `ProtocolVersion` also provides `Http10`, `Http2`, and `Http3`.
These values describe metadata; they do not negotiate a transport.

`StatusCode` is an integer-backed enum with cases such as `Ok`, `Created`, `NoContent`, `BadRequest`, `NotFound`,
`MethodNotAllowed`, `UnprocessableContent`, and `InternalServerError`. Names use current terminology from the
[IANA status registry](https://www.iana.org/assignments/http-status-codes/). Response constructors and `withStatusCode()`
accept enum cases rather than integers. Read `$response->statusCode->value` for the numeric status.

Convert external integers with `StatusCode::tryFrom($code)`, which returns null for unsupported codes, or
`StatusCode::from($code)`, which throws PHP's `ValueError`. Arbitrary numeric status codes are not accepted.

Construction does not read bodies, emit output, decode JSON, infer `Content-Type` or `Content-Length`, or synchronize
`Host` with the URI. Message values do not validate header-specific semantics or transfer framing. At a server boundary,
validate framing and apply protocol rules, including suppressing content for HEAD and statuses that forbid a body.

## Work with headers

`Headers` accepts a map from field names to strings or non-empty lists of strings:

```php
$headers = new Headers([
    'Accept' => ['application/json', 'text/plain'],
    'Set-Cookie' => ['session=abc; HttpOnly', 'theme=dark'],
]);

$cookies = $headers->get('set-cookie'); // Two separate values.
$changed = $headers->withAdded('Set-Cookie', 'language=en');
$removed = $headers->without('Accept');
```

Lookup is case-insensitive. Constructor entries with differently cased spellings of the same name merge, retaining the
first spelling and value order. `all()` returns the original name spelling with value lists. `get()` returns `[]` for a
missing field; a present empty field value is represented by `['']`.

`with()` replaces a field and uses the supplied spelling, placing it at the end of the collection. `withAdded()` appends
values and retains an existing field's spelling and position. `without()` removes the field. None modify the original.

Names must be non-empty HTTP tokens. Values must be strings; prohibited control bytes, including CR, LF, NUL, and DEL,
are rejected. Surrounding spaces and tabs are trimmed, while internal tabs and opaque high bytes are retained. Values
are never automatically joined by commas, preserving fields such as `Set-Cookie`. These syntax rules follow
[HTTP field semantics](https://www.rfc-editor.org/rfc/rfc9110.html#section-5).

## Preserve URI and request-target syntax

`Uri` validates URI references through PHP's native
[RFC 3986 parser](https://www.php.net/manual/en/class.uri-rfc3986-uri.php). It accepts both relative and absolute references.
Its `path()`, `query()`, and `fragment()` accessors return encoded components without decoding. `toString()` returns the
exact constructor input. Host and scheme spelling, repeated query parameters, dot segments, and trailing slashes are
preserved. Absent queries or fragments return null; explicitly empty components return `''`.

`Request` imposes HTTP-specific constraints: absolute references require HTTP(S) and a non-empty host; paths must start
with `/` or be empty. User information and fragments are rejected. Explicit ports must be in the range 0–65535.
Network-path references with a non-empty host are also accepted.

`Request::target()` returns the origin-form path and query, using `/` for an empty path. It retains an empty query marker.
For `OPTIONS`, `new Uri('*')` produces the asterisk target. For `CONNECT`, use an authority-bearing URI with an explicit
port and no path or query, for example `new Uri('https://example.com:443')`; the target becomes `example.com:443`.
URI replacement revalidates these rules. Host headers remain explicitly controlled by the caller.

## Supply body bytes

`Body\Body` exposes `chunks(): iterable` and `size(): ?int`. Consumers iterate raw byte chunks; transfer framing is not
part of the body. Size is measured in bytes and may be unknown. Iteration can raise `HttpException`, so handle errors
around iteration as well as around the initial method call.

`StringBody` stores immutable bytes, supports repeated independent iteration, and exposes `content()` for direct access.
It yields one chunk for non-empty content and no chunks for an empty body. Its size is always known. Encoding and
serialization are the caller's responsibility.

`Body\StreamBody` borrows an open readable stream and consumes it once from its current position, in chunks of at most
8192 bytes. It never rewinds or closes caller-owned streams. Keep the stream open and do not read or seek it elsewhere
until consumption ends. Repeated, concurrent, abandoned, or failed iteration cannot be restarted. `size()` returns null;
set content headers explicitly when the application knows the length. An empty read before EOF is a failure, so this
implementation is unsuitable for polling nonblocking streams.

```php
use ExtendsSoftware\ExaPHP\Http\Body\StreamBody;

$stream = fopen(__DIR__ . '/download.bin', 'rb');
if ($stream === false) {
    throw new RuntimeException('Could not open the download.');
}
try {
    $response = new Response(body: new StreamBody($stream));
    // Emit the response here, while the borrowed stream remains open.
} finally {
    fclose($stream);
}
```

Other body implementations must document resource ownership and replay behavior. Do not assume an arbitrary body can
be read twice. Message replacement shares the body instance; readonly metadata does not make a consuming body immutable
or clone its resources. Request and response construction never takes ownership of external resources.

## Implement handlers and middleware

Handlers implement `Handler\RequestHandler::handle(Request): Response`. Middleware implements
`Middleware\Middleware::process(Request, RequestHandler): Response` and can delegate or return a response directly.

```php
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Middleware\Middleware;

final class NoStore implements Middleware
{
    public function process(Request $request, RequestHandler $next): Response
    {
        $response = $next->handle($request);

        return $response->withHeaders($response->headers->with('Cache-Control', 'no-store'));
    }
}
```

These boundaries use the concrete request and response classes. They return messages rather than emitting them.
Application code can connect Processing, CQRS, and Logging through injected dependencies; HTTP does not depend on them.
Application exceptions may propagate through these contracts unchanged.

## Run a middleware pipeline

`Middleware\MiddlewarePipeline` implements `RequestHandler`. Construct it with the final handler and an ordered list
of middleware instances:

```php
use ExtendsSoftware\ExaPHP\Http\Middleware\MiddlewarePipeline;

// $handler implements RequestHandler; NoStore is the middleware from the preceding example.
$pipeline = new MiddlewarePipeline($handler, [new NoStore()]);
$response = $pipeline->handle($request);
```

The first middleware is outermost: requests flow through middleware in list order, and delegated responses return in
reverse order. An empty list delegates directly to the final handler. Construction validates registrations without
executing them. Associative arrays and entries that do not implement `Middleware` throw `InvalidMiddlewareException`.

Middleware can pass a replacement request, change the returned response, or return immediately without invoking the
remaining chain. Exceptions and engine errors propagate unchanged unless middleware explicitly intercepts them. The
pipeline does not emit responses or translate failures automatically.

Each request and each call to the supplied next handler starts its chain afresh. Middleware and handler instances are
reused, so application code remains responsible for their state. Repeated middleware registrations execute separately.
Calling the next handler multiple times also executes downstream behavior multiple times; it does not make consuming
bodies replayable or undo application side effects.

## Route requests to handlers

For module-owned route configuration and application wiring, use the
[HTTP integration module](../integration/README.md#register-the-http-module). The examples below show direct construction.

Register a list of `Routing\Route` values with `Routing\SimpleRouter`, then use `Routing\RoutingRequestHandler` as
an HTTP handler. A route binds one method and path pattern to a non-empty handler identifier:

```php
use ExtendsSoftware\ExaPHP\Http\Routing\Route;
use ExtendsSoftware\ExaPHP\Http\Routing\RouteMatch;
use ExtendsSoftware\ExaPHP\Http\Routing\RoutingRequestHandler;
use ExtendsSoftware\ExaPHP\Http\Routing\SimpleRouter;

final class ArticleHandler implements RequestHandler
{
    public function handle(Request $request): Response
    {
        $match = $request->attributes->get(RouteMatch::class);
        $id = $match->parameter('id');

        // Validate $id and call application services here.
        return new Response(body: new StringBody('Article ' . $id));
    }
}

// $resolver implements Handler\HandlerResolver and resolves ArticleHandler::class.
$routing = new RoutingRequestHandler(new SimpleRouter([
    new Route(Method::Get, '/articles/{id}', ArticleHandler::class),
]), $resolver);
$pipeline = new MiddlewarePipeline($routing, [new NoStore()]);
$response = $pipeline->handle(new Request(Method::Get, new Uri('/articles/42')));
```

The example uses the HTTP imports and `NoStore` middleware from earlier sections. Supply a `Handler\HandlerResolver`
that maps identifiers to `RequestHandler` instances. The [Integration guide](../integration/README.md#register-the-http-module)
shows service locator wiring. Identifiers can be class names or application-defined strings, exposed as `Route::$handlerId`.

Routers only match routes. Dispatch resolves the selected handler; 404 and 405 responses never invoke the resolver.
Handler lifetime is controlled by the resolver. Resolution failures use `HandlerResolutionException` and propagate
through dispatch unchanged. A handler identifier can resolve to a `MiddlewarePipeline` for route-specific middleware
that needs access to `RouteMatch`. Global middleware runs before routing, so it does not yet have the match.

### Path patterns and precedence

Paths match exactly, including case, trailing slashes, percent-encoding spelling, repeated slashes, and dot segments.
Query strings and URI hosts do not participate in matching. An empty request path matches `/`.
Placeholders occupy a whole non-empty path segment and use names matching `[A-Za-z_][A-Za-z0-9_]*`, such as `{articleId}`.
Names must be unique within a route. Embedded placeholders, optional segments, wildcards, and regex constraints are not
part of this pattern syntax; all other path segments are literal. Patterns cannot include a query or fragment.

Parameters remain encoded strings. `/articles/abc` matches `/articles/{id}`; `/articles/a%2Fb` supplies `a%2Fb` as one
parameter. Validate and deliberately decode or convert values in your handler. `RouteMatch` exposes the selected `route`
and `parameters`, plus `parameter($name)`, which throws `RouteParameterNotFoundException` for an absent name.

Patterns with more literal segments take priority. Overlapping patterns with equal specificity use registration order.
The winning structural pattern reserves the path across methods: if POST `/articles/new` exists alongside GET
`/articles/{id}`, GET `/articles/new` yields 405 with `Allow: POST`, rather than treating `new` as an ID.

Equivalent patterns for the same method are rejected, even when placeholder names differ (`/{id}` versus `/{name}`).
Different methods can share a structural pattern and use their own parameter names. Routing never executes handlers
while registering or matching.

### Method selection and routing responses

Only explicitly registered methods are supported. HEAD does not fall back to GET, and OPTIONS responses are not
generated automatically. An explicit OPTIONS `*` route handles the asterisk target. CONNECT authority targets are not
supported by this path router and cannot be registered.

`SimpleRouter::match()` returns `RouteMatch` or null. `allowedMethods()` returns the methods of the winning path pattern
in registration order, without duplicates, or an empty list when there is no routed target.
`RoutingRequestHandler` returns an empty 404 response for an unmatched path, or an empty 405 response with an `Allow`
header for an unsupported method. These error responses retain the request protocol version. Matched handler responses
and all execution exceptions propagate unchanged.

### Carry typed request metadata

`RequestAttributes` is an immutable collection indexed by exact concrete class. Its `get()` method has a generic return
type, allowing an IDE to infer `RouteMatch` from `get(RouteMatch::class)`. Missing entries throw
`RequestAttributeNotFoundException`. Non-object entries and duplicate concrete classes in the constructor throw
`InvalidRequestAttributesException`.

Use `$request->withAttribute($object)` to add or replace metadata, or `withAttributes($collection)` to replace the
collection. `RequestAttributes::with()` and `without()` create new collections. All other request replacement methods
preserve attributes. Metadata objects retain their identity and should themselves be immutable.

On dispatch, the routing handler attaches a fresh `RouteMatch`, replacing any previous match and preserving other
metadata. The original request remains unchanged. The match reflects the routing decision; changing a request's URI
later does not automatically recompute its attributes.

## Convert application failures into responses

Place `Middleware\ExceptionHandlingMiddleware` first in the middleware list so it surrounds routing, handler resolution,
handler execution, and subsequent middleware:

```php
use ExtendsSoftware\ExaPHP\Http\ExceptionHandling\DefaultExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Middleware\ExceptionHandlingMiddleware;

$pipeline = new MiddlewarePipeline($routing, [
    new ExceptionHandlingMiddleware(new DefaultExceptionResponseFactory()),
    new NoStore(),
]);
```

The default factory returns status 500 with the plain-text body `Internal Server Error`, a UTF-8 text content type,
and `Cache-Control: no-store`. It retains the request protocol version and never exposes exception messages, codes,
types, or stack traces. It does not log. Successful downstream responses pass through unchanged.

Implement `ExceptionHandling\ExceptionResponseFactory` to choose application-specific status codes and response formats.
For example, assuming your application defines `ArticleNotFound`, a factory can map that failure and delegate others:

```php
use ExtendsSoftware\ExaPHP\Http\ExceptionHandling\ExceptionResponseFactory;

final readonly class ApplicationExceptionResponseFactory implements ExceptionResponseFactory
{
    public function __construct(private ExceptionResponseFactory $fallback)
    {
    }

    public function create(Throwable $exception, Request $request): Response
    {
        if ($exception instanceof ArticleNotFound) {
            return new Response(
                StatusCode::NotFound,
                new Headers(['Content-Type' => 'application/json', 'Cache-Control' => 'no-store']),
                new StringBody('{"error":"Article not found"}'),
                $request->protocolVersion,
            );
        }

        return $this->fallback->create($exception, $request);
    }
}
```

Inject this factory into the middleware with `new DefaultExceptionResponseFactory()` as its fallback. Keep client-facing
messages deliberate; an exception's numeric code is not automatically an HTTP status. Add logging through an
application-owned factory decorator if needed, keeping logging policy outside the HTTP component.

The middleware catches `Throwable`, including engine errors. The factory receives the original failure and the request
available when the exception boundary was entered; it cannot see immutable request replacements made downstream.
A factory failure propagates unchanged without retry or automatic fallback.

This boundary covers synchronous handler execution only. Request creation happens before it, and deferred response-body
reads and response emission happen afterward. Handle those failures at the front controller boundary.

## Serve a request through PHP

Use the server boundary contracts `Server\ServerRequestFactory` and `Server\ResponseEmitter` to isolate the host
runtime. Their PHP adapters can drive the routing or middleware handler assembled above from a front controller:

```php
use ExtendsSoftware\ExaPHP\Http\Server\PhpResponseEmitter;
use ExtendsSoftware\ExaPHP\Http\Server\PhpServerRequestFactory;

// Bootstrap the application and construct $pipeline before this point.
$request = new PhpServerRequestFactory()->create();
$response = $pipeline->handle($request);
new PhpResponseEmitter()->emit($response, $request->method);
```

`PhpServerRequestFactory` reads `$_SERVER` and opens `php://input` without buffering the body. The returned `StreamBody`
retains the input resource; PHP releases it when its references are released. Use `fromServer($server, $body)` when a
server snapshot and body are already available. Method, request target, and protocol are required; unsupported enum
values and malformed metadata fail explicitly.

Origin targets use `HTTP_HOST` and the direct server `HTTPS` flag to construct an absolute URI, preserving encoded
paths and queries. Without Host, ordinary origin targets remain relative; double-slash targets require Host to avoid
ambiguity with URI authorities. Absolute-form, CONNECT authority-form, and OPTIONS asterisk targets are supported.
Forwarded headers remain ordinary headers and never override host or scheme. Configure proxy trust separately.

Headers come from `HTTP_*`, `CONTENT_TYPE`, and `CONTENT_LENGTH`. PHP may already combine repeated incoming fields;
the adapter cannot recover their original boundaries. It does not parse JSON, form fields, cookies, or uploads. PHP may
consume multipart input before user code runs; see the
[PHP input stream documentation](https://www.php.net/manual/en/wrappers.php.php).

`PhpResponseEmitter` replaces queued PHP headers, retains separate values such as `Set-Cookie`, and emits body chunks.
It sets the explicit status after fields so a `Location` header cannot change it. The SAPI controls the wire protocol,
so the response's protocol metadata does not negotiate the connection version. PHP's
[header API](https://www.php.net/manual/en/function.header.php) requires emission before output has been sent.

Always pass the originating request method. HEAD, 204, 205, and 304 responses do not consume their body. The emitter
removes Content-Length for 204 and sets it to zero for 205. Other content lengths remain application-supplied; ensure
they describe the actual representation. Transfer-Encoding is rejected because framing belongs to the server.
Informational responses, protocol upgrades, and successful CONNECT tunnels require a different server adapter.
These restrictions follow [HTTP response semantics](https://www.rfc-editor.org/rfc/rfc9110.html).

Do not print output before emission, including into output buffers. The emitter does not flush or clear buffers, exit,
or retry body reads. A read failure can leave a partially sent response; it cannot be replaced after headers are sent.
Use [exception-handling middleware](#convert-application-failures-into-responses) for failures during handler execution.

## Handle component failures

`HttpException` is the common exception interface. Specific failures under `Http\Exception` are:

- `InvalidHeaderException` for invalid field names, value types, or control bytes.
- `InvalidBodyStreamException` for invalid streams and `BodyReadException` for unsupported or failed stream reads.
- `RequestCreationException` for missing or unsupported server metadata and request input opening failures.
- `ResponseEmissionException` for unsupported responses or failed header emission.
- `HandlerResolutionException` when a handler identifier cannot resolve to a request handler.
- `InvalidUriException` for malformed URI references, retaining the native parser exception as the previous exception.
- `InvalidRequestException` for a URI incompatible with the request method or HTTP target rules.
- `InvalidMiddlewareException` for malformed middleware lists or invalid entries.
- `InvalidRouteException` for malformed patterns or registration lists, and `DuplicateRouteException` for duplicates.
- `InvalidRouteMatchException` for parameters inconsistent with the selected route.
- `RouteParameterNotFoundException` for an absent route parameter.
- `InvalidRequestAttributesException` for invalid metadata and `RequestAttributeNotFoundException` for absent metadata.

Creating invalid values throws exceptions. The exception-handling middleware can translate failures within its
downstream chain; callers outside that chain must choose how to represent them.
