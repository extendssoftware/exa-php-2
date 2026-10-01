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

- [Application](docs/application/README.md): module registration, configuration loading, and service locator creation.
  See the guides for [modules](docs/application/modules.md) and [configuration](docs/application/configuration.md).
- [Service locator](docs/service-locator/README.md): shared object services and constructor injection,
  with extensible resolvers.

## Basic usage

Bootstrap an application using an existing configuration directory. An empty directory is sufficient to get started:

```sh
mkdir -p config
```

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;

require 'vendor/autoload.php';

$application = new Application(__DIR__ . '/config');
$services = $application->bootstrap();

assert($application->config() === $services->get(Configuration::class));
```

Place this script in the project root. Register module classes before calling `bootstrap()` as shown in the
[application guide](docs/application/README.md).
The [service locator](docs/service-locator/README.md) can also be used independently.

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
