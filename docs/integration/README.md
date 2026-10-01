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
service. Configure `cqrs.commands` and `cqrs.queries` as maps from message class names to handler service identifiers.
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
        'commands' => [CreateArticle::class => CreateArticleHandler::class],
        'queries' => [FindArticleTitle::class => FindArticleTitleHandler::class],
    ],
    'services' => [
        CreateArticleHandler::class => new InvokableDefinition(CreateArticleHandler::class),
        FindArticleTitleHandler::class => new InvokableDefinition(FindArticleTitleHandler::class),
    ],
];
```

Each factory creates a new synchronous bus and resolves all handlers in its map immediately. The service locator controls
sharing of handler and bus services. Missing `cqrs` or the relevant handler map produces an empty bus.

A present `cqrs` section and each consumed map must be arrays. Map keys and service identifiers must be non-empty strings.
Invalid configuration, including an incorrect configuration service type, raises
`Integration\Cqrs\Exception\InvalidCqrsConfigurationException`, which implements `IntegrationException`.
The buses validate message classes and resolved handler contracts using the
[CQRS registration rules](../cqrs/README.md#synchronous-registration-rules).

Direct factory calls propagate service-resolution and CQRS registration exceptions unchanged. When called through a
`FactoryDefinition`, the service locator preserves its own exceptions and wraps other failures in
`ServiceResolutionException`, retaining the original failure as the previous exception.

Override either bus definition in an application `/config/*.global.php` file when a different implementation is needed.
See [application configuration](../application/configuration.md) for merge and override rules.
