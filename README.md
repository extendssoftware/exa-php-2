# ExaPHP 2

The successor to the PHP library ExaPHP. The project is in initial development.

## Requirements

- PHP ^8.5
- Composer

## Development setup

Install dependencies from the repository root:

```sh
composer install
```

Source code belongs in `src/` under the `ExtendsSoftware\ExaPHP` namespace.
Tests belong in `tests/` under the `ExtendsSoftware\ExaPHP\Tests` namespace.

## Components

- [Service locator](docs/service-locator.md): resolve shared object services from instance, alias, factory, and class
  definitions, with constructor injection and extensible resolvers.

## Basic usage

Register definitions and their resolvers explicitly. Services are resolved on first request and shared by identifier:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\DefinitionServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\InvokableServiceResolver;

require 'vendor/autoload.php';

$locator = new DefinitionServiceLocator(
    ['cache' => new InvokableDefinition(ArrayObject::class)],
    [new InvokableServiceResolver()],
);

$cache = $locator->get('cache');
assert($cache === $locator->get('cache'));
```

See the [service locator guide](docs/service-locator.md) for configuration, dependencies, exceptions, and extension
points.

## Testing

Run the test suite with PHPUnit:

```sh
vendor/bin/phpunit
```

PHPUnit loads `phpunit.xml.dist` automatically.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for notable changes.

## License

ExaPHP is licensed under the [MIT license](LICENSE).
