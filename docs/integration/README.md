# Integration

The `ExtendsSoftware\ExaPHP\Integration` namespace contains application modules and adapters that connect framework components.
`IntegrationException` is the root exception contract for integration failures.

## Register the CQRS module

Register `CqrsModule` before application bootstrap. The application configuration directory must already exist:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Integration\Cqrs\CqrsModule;

$application = new Application(__DIR__ . '/config');
$application->registerModule(CqrsModule::class);
$serviceLocator = $application->bootstrap();
```

`CqrsModule` loads its service configuration during bootstrap, registering `CommandBus` and `QueryBus` with their
synchronous factories. Resolve either interface through the service locator. Both are shared services and can be
resolved without handler mappings, producing empty buses.
See the [CQRS guide](../cqrs/README.md) for constructing and using the buses directly.

## Configure handlers

`CommandBusFactory::create()` and `QueryBusFactory::create()` receive a `ServiceLocator` and read its `Configuration`
service. Configure `cqrs.command.handlers` and `cqrs.query.handlers` as maps from message class names to handler service
identifiers.
The identifiers may be class names or application-defined strings.

With `CqrsModule` registered, a business module or application configuration file can contain the following.
The `App\Article` classes must be autoloadable;
this example assumes the handlers have no required constructor arguments. Use factory definitions for handlers with
injected dependencies.

```php
<?php

declare(strict_types=1);

use App\Article\CreateArticle;
use App\Article\CreateArticleHandler;
use App\Article\FindArticleTitle;
use App\Article\FindArticleTitleHandler;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;

return [
    'cqrs' => [
        'command' => [
            'handlers' => [CreateArticle::class => CreateArticleHandler::class],
        ],
        'query' => [
            'handlers' => [FindArticleTitle::class => FindArticleTitleHandler::class],
        ],
    ],
    'services' => [
        CreateArticleHandler::class => new InvokableDefinition(CreateArticleHandler::class),
        FindArticleTitleHandler::class => new InvokableDefinition(FindArticleTitleHandler::class),
    ],
];
```

Each factory creates a new synchronous bus and resolves all handlers in its map immediately. The service locator
controls
sharing of handler and bus services. Missing `cqrs` or the relevant handler map produces an empty bus.

A present `cqrs` section and each consumed map must be arrays. Map keys and service identifiers must be non-empty
strings.
Invalid configuration, including an incorrect configuration service type, raises
`Integration\Cqrs\Exception\InvalidCqrsConfigurationException`, which implements `IntegrationException`.
The buses validate message classes and resolved handler contracts using the
[CQRS registration rules](../cqrs/README.md#synchronous-registration-rules).

Direct factory calls propagate service-resolution and CQRS registration exceptions unchanged. When called through a
`FactoryDefinition`, the service locator preserves its own exceptions and wraps other failures in
`ServiceResolutionException`, retaining the original failure as the previous exception.

Override either bus definition in an application `/config/*.global.php` file when a different implementation is needed.
See [application configuration](../application/configuration.md) for merge and override rules.

## Register the Event module

Register `ExtendsSoftware\ExaPHP\Integration\Event\EventModule` before bootstrap to make
`ExtendsSoftware\ExaPHP\Event\EventDispatcher` available as a shared service. The module's service configuration uses
`EventDispatcherFactory::create()` to construct a `SynchronousEventDispatcher`. Without an `events` section, it creates
an empty dispatcher.

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Event\EventDispatcher;
use ExtendsSoftware\ExaPHP\Integration\Event\EventModule;

$application = new Application(__DIR__ . '/config');
$application->registerModule(EventModule::class);
$services = $application->bootstrap();
$dispatcher = $services->get(EventDispatcher::class);
```

The application configuration directory must exist. Business modules contribute keyed listener maps under `events`.
For example, assuming the application classes below are autoloadable and the listener needs no constructor arguments:

```php
<?php

declare(strict_types=1);

use App\Article\ArticleCreated;
use App\Search\UpdateArticleIndex;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;

return [
    'events' => [
        ArticleCreated::class => [
            UpdateArticleIndex::class => UpdateArticleIndex::class,
        ],
    ],
    'services' => [
        UpdateArticleIndex::class => new InvokableDefinition(UpdateArticleIndex::class),
    ],
];
```

Another module can add a different listener key for the same event. Associative maps merge, retaining both
registrations.
Use the listener's service FQCN as both key and value by convention; other non-empty string keys and service IDs are
also
accepted. Numeric listener lists are rejected. Each event may have an empty map.

Keys identify registrations; values identify services to resolve. Reusing a key replaces its value according to the
[configuration merge rules](../application/configuration.md). Distinct keys pointing to the same service cause repeated
invocations. Listener order follows merged map iteration order: new keys append, and replacement of an existing key
retains its position. Application configuration can replace a registration by key or clear an event's map with `[]`.

`EventDispatcherFactory` resolves listener services eagerly and converts each map into a listener list. The dispatcher
then applies its [registration validation and dispatch
rules](../event/README.md#register-listeners-for-synchronous-dispatch).
The factory does not invoke listeners while constructing the dispatcher.

Malformed configuration raises `Integration\Event\Exception\InvalidEventConfigurationException`, implementing
`IntegrationException`. Direct factory calls preserve service-resolution and Event exceptions unchanged. Resolution
through `FactoryDefinition` wraps non-ServiceLocator exceptions in `ServiceResolutionException`, preserving the cause.

Override the `EventDispatcher` service in application configuration to select a different implementation.

## Configure command middleware

Set `cqrs.command.middleware` to an ordered list of middleware service identifiers. Register those services under
`services` using the existing service definitions. For example, when `AuditCommand` and `AuthorizeCommand` are
registered:

```php
'cqrs' => [
    'command' => [
        'middleware' => [AuditCommand::class, AuthorizeCommand::class],
    ],
],
```

The command bus factory resolves these services eagerly and passes them to the bus in list order. The first entry wraps
all subsequent entries and the handler. A missing list means no middleware. Invalid configuration uses
`InvalidCqrsConfigurationException`; a resolved object that does not implement `CommandMiddleware` is rejected by the
bus
with `InvalidCommandMiddlewareException`. As with other factory failures, service resolution may wrap that exception.

Configure the pipeline at application level: numeric lists replace earlier lists during merging, rather than append.
See [command middleware](../cqrs/README.md#command-middleware) for execution and context semantics.

## Configure query middleware

Set `cqrs.query.middleware` to an ordered list of query middleware service identifiers. Register the referenced services
under `services`; they must implement `QueryMiddleware`. For example, with the application services below registered:

```php
'cqrs' => [
    'query' => [
        'handlers' => [FindArticleTitle::class => FindArticleTitleHandler::class],
        'middleware' => [AuthorizeQuery::class, CacheQuery::class],
    ],
],
```

The factory resolves handlers and middleware eagerly. Middleware runs in list order, with the first entry outermost.
A missing query section, handler map, or middleware list defaults to empty. Present sections must be arrays, and
middleware
must be a list of non-empty service identifiers. Malformed configuration raises `InvalidCqrsConfigurationException`;
invalid resolved middleware raises `InvalidQueryMiddlewareException` when the bus is constructed.

As with command middleware, configure the ordered pipeline at application level because lists replace earlier lists.
The handler map now lives at `cqrs.query.handlers`; move registrations previously stored under `cqrs.queries` there.
See [query middleware](../cqrs/README.md#query-middleware) for result and context semantics.

## Register the Logging module

Register `Integration\Logging\LoggingModule` before bootstrap. The application configuration directory must exist:

```php
use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Integration\Logging\LoggingModule;
use ExtendsSoftware\ExaPHP\Logging\Logger;

$application = new Application(__DIR__ . '/config');
$application->registerModule(LoggingModule::class);
$services = $application->bootstrap();
$logger = $services->get(Logger::class);
```

The module registers shared `Logger` and `Logging\Writer\LogWriter` services. `LoggerFactory` resolves `LogWriter`
and creates a `WriterLogger`. The default writer is a `StreamLogWriter` targeting `php://stderr` with NDJSON formatting.
Resolving these services does not write a record or open the destination.

Override `LogWriter::class` in an application file such as `/config/logging.global.php` to configure the destination:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Logging\Writer\LogWriter;
use ExtendsSoftware\ExaPHP\Logging\Writer\StreamLogWriter;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;

return [
    'services' => [
        LogWriter::class => new FactoryDefinition(
            static fn(): LogWriter => new StreamLogWriter(__DIR__ . '/../var/application.ndjson'),
        ),
    ],
];
```

Create the `var` directory before logging. Writer factories can construct `FilteringLogWriter` and `CompositeLogWriter`
combinations or resolve other registered writers. See the [logging guide](../logging/README.md) for composition and
failure behavior. No separate logging configuration schema is required. Override `Logger::class` itself to use a
different logger implementation.

A writer service that does not implement `LogWriter` causes
`Integration\Logging\Exception\InvalidLoggingConfigurationException`, which implements `IntegrationException`.
Direct `LoggerFactory::create()` calls preserve service-resolution exceptions unchanged. Resolution through
`FactoryDefinition` wraps non-ServiceLocator exceptions in `ServiceResolutionException`, preserving the cause.

## Register the HTTP module

Register `HttpModule` before modules that contribute HTTP middleware, so its default exception boundary is outermost.
The application configuration directory must exist:

```php
use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Integration\Http\HttpModule;

$application = new Application(__DIR__ . '/config');
$application->registerModule(HttpModule::class);
// Register your business modules here.
$serviceLocator = $application->bootstrap();
```

The module supplies these shared services:

| Service identifier | Default implementation |
| --- | --- |
| `Http\Handler\RequestHandler` | `MiddlewarePipeline` wrapping the routing handler |
| `Http\Routing\Router` | `SimpleRouter` using `http.routes` |
| `Http\Handler\HandlerResolver` | `ServiceLocatorHandlerResolver` |
| `Http\Routing\RoutingRequestHandler` | Routing and lazy handler dispatch |
| `Http\Middleware\ExceptionHandlingMiddleware` | Configured exception response factory |
| `Http\ExceptionHandling\ExceptionResponseFactory` | `DefaultExceptionResponseFactory` |
| `Http\Server\ServerRequestFactory` | `PhpServerRequestFactory` |
| `Http\Server\ResponseEmitter` | `PhpResponseEmitter` |

Identifiers above are relative to `ExtendsSoftware\ExaPHP`. Override service definitions in application configuration
for custom routers, exception responses, or server adapters. Resolving the pipeline validates configuration and resolves
middleware eagerly; handler services are resolved only after a route matches. An empty router returns 404.

### Configure routes and middleware

Each module can contribute a configuration file under its own configuration directory. Application overrides belong
in `/config/*.global.php` or `*.local.php`. For an autoloadable, constructorless application `ArticleHandler` implementing
`RequestHandler`, a configuration file can contain:

```php
use ExtendsSoftware\ExaPHP\Http\Method;
use ExtendsSoftware\ExaPHP\Http\Routing\Route;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;

return [
    'http' => [
        'routes' => [
            'articles.show' => new Route(Method::Get, '/articles/{id}', ArticleHandler::class),
        ],
    ],
    'services' => [
        ArticleHandler::class => new InvokableDefinition(ArticleHandler::class),
    ],
];
```

Use `FactoryDefinition` for handlers with dependencies. Route names are configuration identifiers only; they do not
provide URL generation or become route-match names. The router receives the values in registration order and applies
its normal [matching rules](../http/README.md#path-patterns-and-precedence). Duplicate method/path structures still fail,
even when their configuration names differ.

`http.middleware` is an ordered map from non-empty registration names to non-empty service identifiers. The module
provides `['exceptions' => ExceptionHandlingMiddleware::class]`. Business modules can append entries such as
`['audit' => AuditMiddleware::class]` and register those services. Middleware must implement the HTTP `Middleware` contract.
The first registration runs outermost. Reusing a service under multiple names runs it multiple times.

Associative configuration merges by name. Replacing an existing route or middleware entry retains its position;
new names append in source order. An explicit empty array replaces the section and clears registrations, including the
default exception middleware when applied to `http.middleware`. To rebuild an order entirely, clear the section in an
earlier application configuration file and supply the replacement map in a later file.

Do not use a numeric list for routes or middleware. Factories reject malformed sections or entries with
`InvalidHttpConfigurationException`, which implements `IntegrationException`. They propagate HTTP registration errors
and service resolution failures unchanged. When invoked through the service locator, non-locator factory failures
are wrapped in `ServiceResolutionException`, with the original cause available through `getPrevious()`. Failures
constructing the pipeline occur before its exception middleware can run.

### Run the configured pipeline

After the bootstrap shown above, a front controller can execute:

```php
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Server\ResponseEmitter;
use ExtendsSoftware\ExaPHP\Http\Server\ServerRequestFactory;

try {
    $request = $serviceLocator->get(ServerRequestFactory::class)->create();
    $response = $serviceLocator->get(RequestHandler::class)->handle($request);
    $serviceLocator->get(ResponseEmitter::class)->emit($response, $request->method);
} finally {
    $application->shutdown();
}
```

The default exception boundary converts downstream failures to a generic 500. Request creation, pipeline construction,
emission, and application shutdown remain front-controller concerns. See the
[HTTP server guide](../http/README.md#serve-a-request-through-php) for stream ownership and emission limitations.

### Resolve HTTP handlers

For manual wiring without `HttpModule`, construct `Http\Resolver\ServiceLocatorHandlerResolver` from this Integration
namespace with the application's service locator and pass it to `RoutingRequestHandler` alongside a router.
The adapter checks that resolved services implement `RequestHandler`. A wrong type or service locator failure produces
HTTP `HandlerResolutionException`, preserving the locator failure as the previous exception. Service definitions control
handler sharing and construction; neither matching nor 404/405 responses resolve handlers.
