# Authorization

The `ExtendsSoftware\ExaPHP\Authorization` component evaluates access through application-provided rules. Pass an
[Identity actor](../identity/README.md), an action, and an optional resource together as an `AuthorizationRequest`.

## Check access

Inject an application implementation of `Authorizer` when you need a boolean decision:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Authorization\AuthorizationRequest;

// $authorizer implements Authorizer; $actor is established by a trusted entry point.
// $article is the application resource being evaluated.
$request = new AuthorizationRequest(actor: $actor, action: 'article.update', resource: $article);
$allowed = $authorizer->isGranted($request);
```

Actions are non-empty, case-sensitive, application-defined strings, preserved without trimming or normalization.
Omit the resource for actions without a particular target, such as `article.create`. The request is readonly but holds
its original resource reference; it does not freeze the resource's state. Evaluate access against the state relevant
to the operation being performed.

## Require access

Use `AuthorizationGuard` when the operation must stop on denial:

```php
use ExtendsSoftware\ExaPHP\Authorization\AuthorizationGuard;

$guard = new AuthorizationGuard($authorizer);
$guard->assertGranted($request);

// Perform the authorized operation only after the guard returns normally.
```

The guard evaluates each request once and throws `Exception\AccessDeniedException` when access is denied. The exception
retains the denied request in its readonly `request` property. Evaluation exceptions and engine errors propagate
unchanged, allowing callers to distinguish denial from a failure to evaluate permissions.

## Implement application rules

Implement `Authorizer::isGranted()` with your application's permission rules. Return `false` for denied or unsupported
requests, including unsupported actions or resource types. Do not modify the resource during evaluation. Let evaluation
failures propagate instead of granting access when a decision cannot be made.

For example, this authorizer permits a particular system actor to run a resource-free maintenance action:

```php
use ExtendsSoftware\ExaPHP\Authorization\AuthorizationRequest;
use ExtendsSoftware\ExaPHP\Authorization\Authorizer;
use Override;

$authorizer = new class implements Authorizer {
    #[Override]
    public function isGranted(AuthorizationRequest $request): bool
    {
        return $request->actor->kind === 'system'
            && $request->actor->id === 'scheduler'
            && $request->action === 'maintenance.run'
            && $request->resource === null;
    }
};
```

Actor identifiers are scoped to their kind: include both in identity-based rules. Supply actors through authentication
or a trusted entry point; constructing an actor or authorization request does not establish its authenticity.
Roles, ownership checks, tenant boundaries, and permission storage belong to the application's authorizer.

## Handle failures

Component exceptions implement `AuthorizationException`:

- `Exception\AccessDeniedException`: the authorizer denied access; inspect `request` for the evaluated context.
- `Exception\InvalidAuthorizationRequestException`: the supplied action was empty.

Application evaluation failures do not need to implement `AuthorizationException`. Catch `AccessDeniedException` when
presenting an access denial, and handle evaluation failures separately at the application's error boundary. The denial
message omits actor and resource data, although the retained request remains available to application code.

## Register application and HTTP integration

Register `Integration\Authorization\AuthorizationModule` to provide the shared `AuthorizationGuard` service and the
access-denial exception mapper. For HTTP applications, register it alongside `HttpModule`:

```php
use ExtendsSoftware\ExaPHP\Integration\Authorization\AuthorizationModule;
use ExtendsSoftware\ExaPHP\Integration\Http\HttpModule;

// $application is your Application instance, before bootstrap.
$application->registerModule(HttpModule::class);
$application->registerModule(AuthorizationModule::class);
```

To resolve the shared guard, register your application authorizer in application configuration. In this example,
`ApplicationAuthorizer` implements `Authorizer`; its constructor dependencies must also be registered:

```php
use App\Authorization\ApplicationAuthorizer;
use ExtendsSoftware\ExaPHP\Authorization\Authorizer;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ReflectionDefinition;

return ['services' => [
    Authorizer::class => new ReflectionDefinition(ApplicationAuthorizer::class),
]];
```

The guard is resolved lazily. The module supplies no default authorizer; resolving the shared guard without one fails
service resolution. Applications using module-specific guards can configure those services explicitly and use only the
HTTP mapping. The guard also works without registering `HttpModule`.

With the default HTTP exception pipeline, `AccessDeniedException` becomes a 403 response with this body:

```json
{"type":"about:blank","title":"Forbidden","status":403}
```

Responses use `Content-Type: application/problem+json` and `Cache-Control: no-store`. Actor, action, resource,
request URL, and exception details are omitted. Only the original `AccessDeniedException` is mapped; invalid authorization
requests and evaluation failures continue through the existing exception mappers and default server-error handling.

The mapper is registered under `http.exceptionMappers['authorization.accessDenied']`. Override that entry with an
application mapper service to customize denial responses. Existing mapper ordering applies: the first mapper returning
a problem wins. Replacing the HTTP exception response factory bypasses this mapping unless the replacement uses it.
See [module exception mappers](../integration/README.md#register-module-exception-mappers) for configuration details.
