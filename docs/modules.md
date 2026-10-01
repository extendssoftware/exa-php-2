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

Register `BlogModule::class` with [Application](application.md) before bootstrap. Module constructors must have no
required arguments and should remain lightweight. Put service construction in configuration factories, as shown in the
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
