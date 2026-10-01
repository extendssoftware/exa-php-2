# Application

`Application` bootstraps registered modules, loads configuration, and creates a service locator. The configuration and
module types belong to `ExtendsSoftware\ExaPHP\Application`; the locator remains a separate component.

## Bootstrap an application

After installing Composer dependencies, create a bootstrap script in the project root. This example assumes an
autoloadable `App\Blog\BlogModule` implementing `Module`; see the [module guide](modules.md) for its implementation:

```php
<?php

declare(strict_types=1);

use App\Blog\BlogModule;
use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;

require 'vendor/autoload.php';

$application = new Application(__DIR__ . '/config');
$application->registerModule(BlogModule::class);

$services = $application->bootstrap();
$configuration = $application->config();

assert($configuration === $services->get(Configuration::class));
assert($services === $application->bootstrap());
```

The application configuration directory and each configurable module directory must exist and be readable. Empty
directories are valid, and an application may bootstrap without registered modules.

`registerModule()` records classes in registration order. It may autoload a class but does not construct it. Each class
must implement `Module`, be instantiable, and have no required constructor arguments. Registering the same canonical
class twice raises `DuplicateModuleException`, including registrations through class aliases or differently cased names.

## Bootstrap lifetime

On the first `bootstrap()` call, registration closes and modules are instantiated in registration order. The application
then delegates configuration loading to `ConfigurationLoader` and locator creation to `ServiceLocatorFactory`. Services
are resolved lazily through the returned locator; successful bootstrap does not validate every dependency graph.

After success, repeat calls return the same locator without reconstructing modules or reloading configuration.
`config()` returns the exact `Configuration` instance registered in that locator. See the [configuration
guide](configuration.md) for lookups, file precedence, merging, and service definitions.

Calling `config()` before successful bootstrap raises `ApplicationStateException`. Registration remains closed after
bootstrap starts, whether it succeeds or fails. A failed attempt exposes no partial configuration and cannot be retried
on that instance: fix the cause and create a new `Application`. Constructor and configuration-file side effects are not
rolled back. A second bootstrap call while the first is still in progress is also rejected.

## Configure bootstrap collaborators

The constructor's second and third arguments are `ConfigurationLoader` and `ServiceLocatorFactory`. Defaults support
construction with only a directory. You may also supply explicitly constructed collaborators:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationLoader;
use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationMerger;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;

require 'vendor/autoload.php';

$application = new Application(
    configDirectory: __DIR__ . '/config',
    configurationLoader: new ConfigurationLoader(new ConfigurationMerger()),
    serviceLocatorFactory: new ServiceLocatorFactory(),
);
```

## Handle bootstrap failures

Catch `ExtendsSoftware\ExaPHP\Application\ApplicationException` for component failures. Specific exceptions live in
`ExtendsSoftware\ExaPHP\Application\Exception`:

| Exception | Cause |
| --- | --- |
| `InvalidModuleException` | A class is missing, is not an instantiable module, or requires constructor arguments. |
| `DuplicateModuleException` | A module class is already registered. |
| `ApplicationStateException` | An operation is invalid for the application's bootstrap state. |
| `ModuleInstantiationException` | Module construction raised a non-application exception or engine error. |

`ModuleInstantiationException` names the module and preserves the original cause through `getPrevious()`. Application
exceptions from module constructors propagate unchanged. Configuration and locator creation failures also propagate
without an additional bootstrap wrapper; see [configuration failures](configuration.md#handle-configuration-failures).

Other autoloading failures during registration and exceptions or errors from a module's `configDirectory()` method
propagate unchanged. Exceptions during later service lookup follow the [service locator
contract](../service-locator/README.md#handle-failures).
