# Application configuration

Application configuration combines module defaults with application overrides. `Configuration` provides read-only
lookup; `ConfigurationLoader` loads PHP files, and `ConfigurationMerger` combines their returned arrays. These classes
live in `ExtendsSoftware\ExaPHP\Application\Configuration`.

## Read configuration

After [bootstrap](application.md), use `$application->config()` or retrieve `Configuration::class` from the locator. You
can also create configuration directly:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;

require 'vendor/autoload.php';

$configuration = new Configuration([
    'blog' => ['timeout' => 10, 'token' => null],
    'servers' => [['host' => 'localhost']],
]);

assert($configuration->get('blog.timeout') === 10);
assert($configuration->has('blog.token'));
assert($configuration->get('blog.token') === null);
assert(!$configuration->has('blog.missing'));
assert($configuration->get('servers.0.host') === 'localhost');
```

`get()` returns the original value without conversion, including arrays and objects. `has()` distinguishes missing
values from present `null`, `false`, zero, or empty arrays. Use `has()` to decide whether to supply an
application-specific fallback.

Paths are case-sensitive dot-separated keys and are not trimmed. They traverse arrays only, including numeric indexes;
object properties cannot be traversed. A missing segment or a non-array intermediate value makes `has()` return `false`
and `get()` throw `ConfigurationNotFoundException`.

Empty paths, leading or trailing dots, and empty middle segments are invalid for both methods. Literal dots in keys
cannot be escaped; retrieve the containing section and index it directly, for example
`$configuration->get('services')['a.b']`.

The configuration object offers no mutation methods. Ordinary changes to input or returned arrays do not replace stored
values. Objects retain their identity and mutability, and PHP array references remain references. Use immutable values
and arrays without references when an immutable snapshot is required.

## Load files

`Application` loads configuration automatically. Files are processed in this order:

1. Each registered configurable module's `configDirectory()`, loading names ending in `.php`.
2. Application directory files ending in `.global.php`.
3. Application directory files ending in `.local.php`.

Modules retain registration order. Within each group, filenames are sorted alphabetically using case-sensitive string
ordering. Discovery is non-recursive. Every global file precedes every local file, regardless of filename.

Configured directories must exist and be readable, but may be empty. Individual files are optional. Every loaded file
must return an array; a file without a return statement is invalid. For example, `config/blog.global.php`:

```php
<?php

declare(strict_types=1);

return [
    'blog' => ['timeout' => 20],
];
```

Commit global configuration and local templates, keeping actual local overrides out of Git:

| File | Loaded | Git convention |
| --- | --- | --- |
| `config/blog.global.php` | Yes | Commit |
| `config/blog.local.php` | Yes, after globals | Ignore |
| `config/blog.local.php.dist` | No | Commit as a template |
| `config/blog.php` | No | Does not match an application suffix |

Use `/config/*.local.php` in the application's `.gitignore`. Copy a `.local.php.dist` template to `.local.php` and
customize the values for your environment. Module directories likewise exclude files ending in `.php.dist`.

For direct loading, instantiate modules first. Given the autoloadable `BlogModule` from the [module guide](modules.md):

```php
<?php

declare(strict_types=1);

use App\Blog\BlogModule;
use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationLoader;
use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationMerger;

require 'vendor/autoload.php';

$loader = new ConfigurationLoader(new ConfigurationMerger());
$configuration = $loader->load([new BlogModule()], __DIR__ . '/config');
```

Modules without `ConfigurableModule` are skipped, and duplicate module classes are rejected. Each direct `load()` call
executes files again in an isolated local scope without the loader instance. Configuration files are executable PHP;
their side effects are not undone if loading fails.

## Merge rules

Root configuration arrays are maps of sections. Later sources add or replace values according to these rules:

- Two associative arrays merge recursively by key. Sparse numeric arrays are maps and retain their keys.
- Lists have consecutive integer keys starting at zero. If either value is a list, the incoming value replaces the old
  one.
- Scalars, objects, and `null` replace earlier values as a whole. Objects are not cloned.
- An empty array is a list: it clears an ordinary section when used as a replacement. An empty source changes nothing.
- The root `services` section is always merged by identifier. A definition is replaced as a whole, never merged
  internally. An empty service map preserves registrations rather than clearing them.

Files from one module are combined before modules are merged. Later files within that module can override earlier
service definitions. Two different modules defining the same identifier raise `DuplicateServiceDefinitionException`,
even if the definitions are identical. An application override cannot conceal that conflict.

Application global and local sources can add service definitions or replace module definitions. Later application
sources can also override earlier ones. This makes application-level customization explicit without changing module
code.

To merge already loaded arrays directly:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationMerger;

require 'vendor/autoload.php';

$merger = new ConfigurationMerger();
$blog = $merger->mergeSources([
    'defaults.php' => ['blog' => ['timeout' => 10, 'tags' => ['news', 'updates']]],
    'settings.php' => ['blog' => ['timeout' => 15]],
]);
$configuration = $merger->merge(
    moduleConfigurations: ['Blog' => $blog],
    applicationConfigurations: ['blog.local.php' => ['blog' => ['tags' => ['local']]]],
);

assert($configuration->get('blog.timeout') === 15);
assert($configuration->get('blog.tags') === ['local']);
```

`mergeSources()` returns an array for sources belonging to one module or override collection. `merge()` takes module
arrays keyed by module name and optional application arrays keyed by source name, and returns `Configuration`. Both
preserve the supplied iteration order; names provide error context, not file loading or sorting. Each merge is
independent.

The merger validates that a present `services` section is an array but does not validate or execute its definitions.

## Provide services

Use the root `services` key for `ServiceDefinition` objects indexed by service identifier. For example, this file can be
loaded as `config/timezone.global.php` or as a module configuration file:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;

return [
    'timezone' => 'Europe/Amsterdam',
    'services' => [
        DateTimeZone::class => new FactoryDefinition(
            static fn(ServiceLocator $services): DateTimeZone =>
                new DateTimeZone($services->get(Configuration::class)->get('timezone')),
        ),
    ],
];
```

The factory receives the final merged configuration through the locator and passes only the required value to its
service. `Configuration::class` is reserved: application and module configuration cannot replace that service, even with
`null`.

`Application` delegates locator creation to `ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory`. It can
also be used directly:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;

require 'vendor/autoload.php';

$configuration = new Configuration(['services' => ['cache' => new InvokableDefinition(ArrayObject::class)]]);
$services = new ServiceLocatorFactory()->create($configuration);

assert($services->get('cache') instanceof ArrayObject);
assert($configuration === $services->get(Configuration::class));
```

An omitted or empty `services` map is valid. Each supplied entry must implement `ServiceDefinition`; raw class names,
objects, arrays, and callables must be expressed through the appropriate definition. The factory installs instance,
alias, factory, invokable, and reflection resolvers and registers the exact configuration object as an instance service.
See the [service locator guide](service-locator.md) for these definitions and constructor injection rules.

Creation is lazy: it does not instantiate configured classes, invoke service factories, or validate dependency graphs.
Each factory call creates a separate locator without modifying the configuration. For custom resolvers, construct
`DefinitionServiceLocator` directly using its [extension API](service-locator.md#extend-service-resolution).

## Handle configuration failures

These exceptions live in `ExtendsSoftware\ExaPHP\Application\Exception` and implement
`ExtendsSoftware\ExaPHP\Application\ApplicationException`:

| Exception | Cause |
| --- | --- |
| `ConfigurationNotFoundException` | `get()` cannot reach the requested path. |
| `InvalidConfigurationPathException` | A lookup path is empty or contains an empty segment. |
| `ConfigurationLoadException` | Directory discovery or configuration-file execution fails. |
| `InvalidConfigurationException` | Non-array file result or service map, or duplicate modules passed to the loader. |
| `DuplicateServiceDefinitionException` | Different modules define the same service identifier. |
| `InvalidServiceDefinitionException` | Locator creation encounters an entry that is not a `ServiceDefinition`. |
| `ReservedServiceDefinitionException` | Configuration attempts to supply the reserved configuration service. |

Load failures identify the file or directory; service conflicts name the identifier and both modules. File execution
exceptions and engine errors are wrapped in `ConfigurationLoadException` with the original cause in `getPrevious()`. PHP
warnings (`E_WARNING` and `E_USER_WARNING`) become `ErrorException` and are wrapped in the same way. The previous error
handler is restored after file execution. Failures from `configDirectory()` propagate unchanged.

Once bootstrap succeeds, later service lookup failures follow the [service locator
contract](service-locator.md#handle-failures).
