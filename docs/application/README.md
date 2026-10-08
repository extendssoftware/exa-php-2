# Application

`Application` bootstraps registered modules, loads configuration, creates a service locator, and coordinates module
hooks. The configuration and module types belong to `ExtendsSoftware\ExaPHP\Application`; the locator remains a separate
component.

## Set up application development instructions

Use the [application AGENTS template](AGENTS.template.md) as a starting point for projects using ExaPHP. Copy it into
your application's root as `AGENTS.md`, then adapt the module layout, tooling, and operational guidance to your project.
The template contains application-focused architecture guidance and the framework's shared coding conventions.

For a standard Composer installation, run this from the application root if it does not already have an `AGENTS.md`:

```sh
cp vendor/extendssoftware/exa-php-2/docs/application/AGENTS.template.md AGENTS.md
```

If an instructions file already exists, merge the relevant guidance instead of replacing it. Framework maintainers
review shared conventions when updating their `AGENTS.md`; synchronization is not automatic. Application copies remain
independent, so review template changes when upgrading the framework and preserve your application-specific rules.

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
try {
    $configuration = $application->config();
    assert($configuration === $services->get(Configuration::class));
    assert($services === $application->bootstrap());
} finally {
    $application->shutdown();
}
```

The application configuration directory and each configurable module directory must exist and be readable. Empty
directories are valid, and an application may bootstrap without registered modules.

`registerModule()` records classes in registration order. It may autoload a class but does not construct it. Each class
must implement `Module`, be instantiable, and have no required constructor arguments. Registering the same canonical
class twice raises `DuplicateModuleException`, including registrations through class aliases or differently cased names.

## Bootstrap lifetime

On the first `bootstrap()` call, registration closes and modules are instantiated in registration order. The application
then delegates configuration loading to `ConfigurationLoader` and locator creation to `ServiceLocatorFactory`. It runs
`BootstrapModule::bootstrap()` hooks in registration order using that locator. Only after every hook succeeds does
bootstrap return the locator and make `config()` available. Hooks can obtain configuration through the locator. Services
remain lazy unless requested by a hook; successful bootstrap does not validate every dependency graph.

While the application is running, repeat calls return the same locator without rerunning hooks or loading configuration.
`config()` returns the exact `Configuration` instance registered in that locator. See the [configuration
guide](configuration.md) for lookups, file precedence, merging, and service definitions.

Calling `config()` before successful bootstrap raises `ApplicationStateException`. Registration remains closed after
bootstrap starts, whether it succeeds or fails. A failed attempt exposes no partial configuration and cannot be retried
on that instance: fix the cause and create a new `Application`. A second bootstrap call while the first is still in
progress is also rejected.

If a bootstrap hook fails, previously completed modules are shut down automatically in reverse order. A module without a
bootstrap hook counts as started when its position in registration order is reached. The failing module and modules not
yet reached receive no shutdown call; the failing hook must clean up its own partial setup. Failures before the hook
phase, including constructor and configuration failures, do not run shutdown hooks. Those side effects are not rolled
back.

## Shut down explicitly

Call `shutdown()` when application execution ends, typically in a `finally` block as above. It runs `ShutdownModule`
hooks on the same module instances and with the same locator, in reverse registration order. The runtime must call this
method; Application does not register a PHP shutdown function or destructor hook.

All eligible hooks are attempted, including when a hook throws an exception or engine error. Each hook is attempted at
most once. Repeat shutdown calls after completion or failed bootstrap do nothing, even if an earlier shutdown reported
failures. Calls before bootstrap or while bootstrap or shutdown is running raise `ApplicationStateException`.

After shutdown, registration and bootstrap remain closed. Configuration from successful bootstrap stays available
through `config()`. A previously returned locator is not invalidated, but services whose resources were closed must not
be reused. See [module hooks](modules.md#add-lifecycle-hooks) for implementing these optional capabilities.

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

Catch `ExtendsSoftware\ExaPHP\Application\ApplicationException` for component failures. Module registration and lifecycle exceptions live in
`ExtendsSoftware\ExaPHP\Application\Module\Exception`. `ApplicationStateException` remains in
`ExtendsSoftware\ExaPHP\Application\Exception`; configuration and service-definition exceptions live in
`ExtendsSoftware\ExaPHP\Application\Configuration\Exception`:

| Exception | Cause |
| --- | --- |
| `InvalidModuleException` | A class is missing, is not an instantiable module, or requires constructor arguments. |
| `DuplicateModuleException` | A module class is already registered. |
| `ApplicationStateException` | An operation is invalid for the application's bootstrap state. |
| `ModuleInstantiationException` | Module construction raised a non-application exception or engine error. |
| `ModuleBootstrapException` | A bootstrap hook failed; completed modules have been offered cleanup. |
| `ModuleShutdownException` | One or more shutdown hooks failed; all eligible hooks have been attempted. |

`ModuleInstantiationException` names the module and preserves the original cause through `getPrevious()`. Application
exceptions from module constructors propagate unchanged. Configuration and locator creation failures also propagate
without an additional bootstrap wrapper; see [configuration failures](configuration.md#handle-configuration-failures).

`ModuleBootstrapException::$module` names the failing hook's module and `getPrevious()` preserves the original failure,
including an application exception or engine error. Its `shutdownFailures` array maps module class names to failures
from automatic cleanup, in shutdown order; cleanup failures never replace the startup cause.

`ModuleShutdownException::$failures` contains all shutdown failures keyed by module class, in shutdown order.
`getPrevious()` is the first failure. Handle this exception inside your `finally` block if an execution error must
remain the primary failure reported by your entry point.

Other autoloading failures during registration and exceptions or errors from a module's `configDirectory()` method
propagate unchanged. Exceptions during later service lookup follow the [service locator
contract](../service-locator/README.md#handle-failures).
