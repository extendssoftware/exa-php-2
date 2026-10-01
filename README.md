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

- [Integration](docs/integration/README.md): application integration modules and configuration-driven CQRS bus factories.
- [Application](docs/application/README.md): modules, lifecycle hooks, configuration, and service locator creation.
  See the guides for [modules](docs/application/modules.md) and [configuration](docs/application/configuration.md).
- [CQRS](docs/cqrs/README.md): command and typed query contracts, synchronous buses, and component exceptions.
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

try {
    assert($application->config() === $services->get(Configuration::class));
} finally {
    $application->shutdown();
}
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

### Container workflow

With Docker Compose and [just](https://just.systems/) installed, run Composer and PHPUnit in the PHP 8.5 CLI container:

```sh
just install
just test
just test tests/Integration/Cqrs/CqrsModuleIntegrationTest.php
just test --filter 'testApplicationBootstrapsWithTheCqrsModule'
```

`just test` forwards all arguments to PHPUnit, including quoted filters. Run `just` to list recipes or `just build`
to build the image explicitly. Install and test recipes build the image as needed and remove their containers afterward.
The repository, including `vendor/`, is mounted into the container. Recipes use your host user and group IDs so generated
files retain your ownership. The native PHP workflow remains available.

Without just, use `docker compose run --build --rm php composer install` and
`docker compose run --build --rm php vendor/bin/phpunit`. Compose defaults to UID/GID 1000; set `LOCAL_UID` and `LOCAL_GID`
to your user and group IDs if needed. On Windows, use these commands through WSL for the same ownership behavior.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for notable changes.

## License

ExaPHP is licensed under the [MIT license](LICENSE).
