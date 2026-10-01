# Application modules

Modules identify explicitly registered parts of an application. Each module can own its configuration and source code.
Composer provides autoloading; registering a module does not configure its namespace or scan directories for modules.

## Define a configurable module

For this layout, configure Composer to map `App\Blog\` to `module/Blog/src/` and regenerate its autoloader:

```text
module/
  Blog/
    config/
      defaults.php
      services.php
    src/
      BlogModule.php
```

Implement both the module marker and its independent configuration capability in `BlogModule.php`:

```php
<?php

declare(strict_types=1);

namespace App\Blog;

use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;

use function dirname;

/**
 * Supplies the Blog module configuration directory.
 */
final readonly class BlogModule implements Module, ConfigurableModule
{
    /**
     * Returns the module configuration directory.
     *
     * @return non-empty-string The absolute configuration directory path.
     */
    public function configDirectory(): string
    {
        return dirname(__DIR__) . '/config';
    }
}
```

`Module` is a marker with no methods. `ConfigurableModule` does not extend it; it adds `configDirectory(): string`.
Return a non-empty absolute path to an existing configuration directory. The directory may be empty.

Register `BlogModule::class` with [Application](README.md) before bootstrap. Module constructors must have no required
arguments and should remain lightweight. Put service construction in configuration factories, as shown in the
[configuration guide](configuration.md#provide-services).

## Define a module without configuration

A shared module providing classes or value objects can implement only `Module`:

```php
<?php

declare(strict_types=1);

namespace App\Shared;

use ExtendsSoftware\ExaPHP\Application\Module\Module;

/**
 * Identifies the shared application module.
 */
final readonly class SharedModule implements Module
{
}
```

It is instantiated during bootstrap, but contributes no configuration files. Map its namespace with Composer just as for
other module code.

## Supply module configuration

Each file directly inside the configuration directory whose name ends in `.php` must return an array. Files are loaded
alphabetically; files nested in subdirectories and `.php.dist` templates are excluded. Later files in the same module
can override its earlier values and service definitions.

Module configuration is combined in registration order, followed by application global and local overrides. Different
modules cannot define the same service identifier. See [loading](configuration.md#load-files) and [merge
rules](configuration.md#merge-rules) for the complete precedence and conflict behavior.

## Add lifecycle hooks

`BootstrapModule` and `ShutdownModule` are independent capabilities in the same namespace as `Module`. Neither extends
`Module` or requires the other capability. Implement `Module` alongside whichever hooks you need. Existing modules need
no changes, and hook-only modules need not supply configuration.

Both hooks receive `ServiceLocator` and return `void`. Use them for startup and cleanup work that configuration and
service factories do not perform. Retain resources on the module when shutdown needs them; the same instance is reused.

For example, assume your application provides an autoloadable `App\Telemetry\Exporter` service with `start()` and
`stop()` methods. Its `start()` method must undo any partial resource acquisition before throwing:

```php
<?php

declare(strict_types=1);

namespace App\Telemetry;

use ExtendsSoftware\ExaPHP\Application\Module\BootstrapModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;
use ExtendsSoftware\ExaPHP\Application\Module\ShutdownModule;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use Throwable;

/**
 * Starts and stops the application telemetry exporter.
 */
final class TelemetryModule implements Module, BootstrapModule, ShutdownModule
{
    /**
     * The exporter retained after successful startup.
     */
    private ?Exporter $exporter = null;

    /**
     * Starts the configured exporter.
     *
     * @param ServiceLocator $services The application services.
     *
     * @return void
     *
     * @throws Throwable When service lookup or exporter startup fails.
     */
    public function bootstrap(ServiceLocator $services): void
    {
        $exporter = $services->get(Exporter::class);
        $exporter->start();
        $this->exporter = $exporter;
    }

    /**
     * Stops the previously started exporter.
     *
     * @param ServiceLocator $services The application services.
     *
     * @return void
     *
     * @throws Throwable When stopping the exporter fails.
     */
    public function shutdown(ServiceLocator $services): void
    {
        try {
            $this->exporter?->stop();
        } finally {
            $this->exporter = null;
        }
    }
}
```

Register `TelemetryModule::class` and define `Exporter::class` in application or module service configuration. Bootstrap
hooks run in registration order; shutdown hooks run in reverse order. A shutdown-only module is eligible once startup
reaches its place in that order. Hooks receive the final locator, including the merged configuration service.

If startup fails, only modules that already completed their startup step receive automatic shutdown. A failing hook is
responsible for its own partial work. Hook failures do not stop remaining cleanup attempts. See the [application
lifecycle](README.md#bootstrap-lifetime) and [failure contract](README.md#handle-bootstrap-failures).
