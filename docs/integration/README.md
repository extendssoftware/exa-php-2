# Integration

The `ExtendsSoftware\ExaPHP\Integration` namespace contains application modules that connect framework components.
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
