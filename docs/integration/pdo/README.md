# PDO integration

`Integration\Pdo\PdoModule` registers a shared `PDO` connection and a `TransactionManager` backed by that connection.
Enable PHP's PDO extension and the driver for your database. The integration tests use `pdo_sqlite`.

## Configure and register the module

Create `config/pdo.local.php` with your application connection settings. Keep credentials in local configuration or
supply them from your application's secret configuration source.

```php
<?php

declare(strict_types=1);

return [
    'pdo' => [
        'dsn' => 'sqlite:/absolute/path/application.sqlite',
        'username' => null,
        'password' => null,
        'options' => [],
    ],
];
```

Register the module before bootstrapping:

```php
use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Integration\Pdo\PdoModule;
use ExtendsSoftware\ExaPHP\Transaction\TransactionManager;

$application = new Application(__DIR__ . '/config');
$application->registerModule(PdoModule::class);
$services = $application->bootstrap();
$pdo = $services->get(PDO::class);
$transactions = $services->get(TransactionManager::class);
```

The DSN is required when the connection is first resolved. Username and password default to `null`. Options must be
an array keyed by integer PDO attribute constants. The factory defaults `PDO::ATTR_ERRMODE` to `PDO::ERRMODE_EXCEPTION`;
explicit error modes must also be `PDO::ERRMODE_EXCEPTION`. Other error modes are rejected with
`InvalidPdoConfigurationException` so SQL failures naturally propagate to the transaction boundary.

The connection is created lazily and shared by the service locator. `TransactionManager` aliases the shared
`Integration\Pdo\Transaction\PdoTransactionManager` service. Inject `PDO` into application repositories to participate
in the same transaction. Application service definitions can replace the default connection or transaction manager.

## Execute transactional work

The following example assumes an existing `articles` table with a `title` column:

```php
$transactions->transactional(static function () use ($pdo): void {
    $statement = $pdo->prepare('INSERT INTO articles (title) VALUES (:title)');
    $statement->execute(['title' => 'A new article']);
});
```

The callback runs once. Its result is returned after commit. Exceptions and engine errors trigger rollback, and a
successful rollback propagates the original failure unchanged. See the [Transaction guide](../../transaction/README.md)
for lifecycle exceptions and [CQRS middleware wiring](../../transaction/README.md#wrap-cqrs-commands).

The manager rejects nested calls and connections with an existing transaction, including transactions started through
another manager using the same connection. A caught rejection leaves the outer transaction usable. Callback code must
leave transaction control to the manager and retain exception error mode. Before every transaction, the manager checks
exception mode on the injected connection; incompatible modes raise `TransactionStartException` before the callback
runs. This also applies to application-provided connections. Use transactional tables and avoid SQL statements that
implicitly commit;
PDO cannot roll back changes already committed by the database. See PHP's
[transaction documentation](https://www.php.net/manual/en/pdo.begintransaction.php).

A failed commit does not trigger an automatic rollback or retry. The outcome is unconfirmed and the connection may
still have an active transaction; application recovery must account for that state before reusing the connection.

## Handle configuration and connection failures

`InvalidPdoConfigurationException` reports invalid configuration, and `PdoConnectionException` reports connection
creation failures. Both live under `Integration\Pdo\Exception` and implement `IntegrationException`. PDO construction
failures retain their original exception as the previous exception. Wrapper messages omit connection details, but
underlying driver exceptions can contain sensitive information.

When resolving through the service locator, factory failures are wrapped in `ServiceResolutionException`; inspect its
previous exception for the integration failure. Transaction lifecycle failures use the Transaction component's exception
contract, with original PDO exceptions retained where available. If rollback also fails, `TransactionRollbackException`
retains both the callback failure and the rollback failure.
