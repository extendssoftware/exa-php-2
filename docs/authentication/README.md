# Authentication

The `ExtendsSoftware\ExaPHP\Authentication` component defines credential verification that returns an authenticated
[Identity actor](../identity/README.md). Applications supply credential objects and an `Authenticator` implementation.

## Authenticate credentials

Inject `Authenticator` into the application service responsible for authentication and pass a `Credentials` object:

```php
<?php

declare(strict_types=1);

// $authenticator implements Authenticator; $credentials implements Credentials.
$actor = $authenticator->authenticate($credentials);
```

Successful return establishes the principal represented by the actor. Use that actor in subsequent application
operations and [authorization requests](../authorization/README.md). Authentication does not grant permissions or
create a session.

## Implement verification

`Credentials` is a marker contract for application-defined credential objects. Define the fields required by your
chosen authentication mechanism and implement `Authenticator::authenticate()` to verify them. Inject dependencies
needed for verification through the constructor. Return an `Actor` only after verification succeeds.

Reject unsupported credential types with `Exception\UnsupportedCredentialsException`. Reject credentials that do not
establish a principal with `Exception\InvalidCredentialsException`. Translate expected operational failures into
`Exception\AuthenticationFailedException`, preserving the original cause as the previous exception. Unexpected
programming errors should propagate rather than being reported as rejected credentials.

Treat credential contents as sensitive. Do not include them in exception messages or retain them as exception
properties. Use PHP's `SensitiveParameter` attribute on concrete implementation parameters receiving credentials
and on constructors receiving secrets to redact those arguments from stack traces. The marker interface does not
provide automatic redaction; avoid logging credential objects and review lower-level exception messages for secrets.

## Handle failures

All component exceptions implement `AuthenticationException`:

- `Exception\AuthenticationFailedException` extends `RuntimeException`: verification could not complete because of
  an operational failure. Keep this distinct from credential rejection when reporting or retrying failures.
- `Exception\InvalidCredentialsException` extends `RuntimeException`: credentials were rejected. Present a generic
  failure without exposing account details or the submitted credentials.
- `Exception\UnsupportedCredentialsException` extends `InvalidArgumentException`: the configured authenticator cannot
  handle the credential type. Check credential extraction and authenticator wiring.

These exceptions accept the standard exception message, code, and previous cause. Their messages are diagnostic data;
choose public error responses separately at the application's transport boundary.

## Require Bearer authentication on HTTP routes

Register `HttpModule` and `Integration\Authentication\AuthenticationModule` before bootstrap. The authentication module
registers the extractor, failure response factory, and middleware; it does not add global middleware or supply a token
verifier. Register your application's `Authenticator` and attach the middleware to protected routes or groups:

```php
use App\Authentication\TokenAuthenticator;
use App\Http\ArticleHandler;
use ExtendsSoftware\ExaPHP\Authentication\Authenticator;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Routing\Route;
use ExtendsSoftware\ExaPHP\Http\Routing\RouteGroup;
use ExtendsSoftware\ExaPHP\Integration\Authentication\Http\AuthenticationMiddleware;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ReflectionDefinition;

return [
    'services' => [
        Authenticator::class => new ReflectionDefinition(TokenAuthenticator::class),
        ArticleHandler::class => new ReflectionDefinition(ArticleHandler::class),
    ],
    'http' => ['routes' => [
        'articles' => new RouteGroup('/articles', [AuthenticationMiddleware::class], [
            new Route('articles.show', Method::Get, '/{id}', ArticleHandler::class),
        ]),
    ]],
];
```

`TokenAuthenticator` and `ArticleHandler` are application classes; register their constructor dependencies as needed.
The verifier receives `Bearer\BearerTokenCredentials` and reads `token()` to validate the token before returning an
`Actor`. Token signatures, expiry, revocation, intended recipients, and principal lookup belong to that verifier.
The framework does not assume a token encoding or storage mechanism. Use HTTPS for Bearer credentials.

The extractor accepts a single `Authorization: Bearer <token>` header. Scheme matching is case-insensitive; tokens are
case-sensitive and preserved exactly. One or more spaces must separate the scheme and token. Repeated header values,
combined Bearer values, missing tokens, and invalid Bearer syntax are rejected. Other authentication schemes are treated
as missing supported credentials. Query parameters and request bodies are not credential sources.

On success, handlers retrieve the authenticated actor through typed request attributes:

```php
use ExtendsSoftware\ExaPHP\Identity\Actor;

$actor = $request->attributes->get(Actor::class);
```

Existing actor metadata never bypasses verification and is replaced on the downstream request after success. The original
request is unchanged. Routes without the middleware remain public; accessing a missing actor follows the existing
request-attribute exception contract. Authentication failures prevent handler construction and execution.

### Authentication responses

`Http\BearerAuthenticationResponseFactory` under `Integration\Authentication` uses the following responses:

| Failure | Status | WWW-Authenticate |
| --- | --- | --- |
| Missing credentials or another scheme | 401 | `Bearer realm="api"` |
| Malformed authentication header | 400 | `Bearer realm="api", error="invalid_request"` |
| Credentials rejected by the authenticator | 401 | `Bearer realm="api", error="invalid_token"` |

Responses contain generic Problem Details, use `application/problem+json` and `Cache-Control: no-store`, and preserve the
request protocol version. Credential contents and verification exception messages are never included. These categories
follow [Bearer authentication error semantics](https://www.rfc-editor.org/rfc/rfc6750.html#section-3.1).

The middleware handles client authentication failures locally so the response can include its scheme's challenge.
`AuthenticationFailedException`, `UnsupportedCredentialsException`, and unexpected errors propagate to the global HTTP
exception boundary, producing server errors by default. Exceptions thrown by downstream handlers are also left to that
boundary; they are not reclassified as token rejection. Authorization remains a separate application check.

### Customize the HTTP scheme

The module registers these services in order:

- `Http\RequestCredentialsExtractor`: `BearerTokenCredentialsExtractor` by default.
- `Http\AuthenticationResponseFactory`: `BearerAuthenticationResponseFactory` by default.
- `Http\AuthenticationMiddleware`: constructed with the extractor, application authenticator, and response factory.

These names are relative to `Integration\Authentication`. Replace the extractor and response factory together when
using another scheme, and configure an authenticator accepting the resulting credentials. An extractor returns `null`
for absent or unsupported credentials and throws `Http\Exception\MalformedCredentialsException` for malformed input.
That exception implements `IntegrationException`. The response factory receives an `AuthenticationFailure` category
and request, enabling custom challenges and public problem descriptions. Override it to customize the default realm.

`BearerTokenCredentials` validates syntax at construction and stores its token in PHP's `SensitiveParameterValue`.
The wrapper hides the token from debug output and prevents PHP serialization of the credentials. The constructor uses
`SensitiveParameter`, and the extractor and middleware mark their request parameters sensitive. These protections do not
redact explicit `token()` calls or request/header logging. Do not log credential contents or Authorization headers,
and mark concrete verifier credential parameters sensitive as well.
