# Transaction

`TransactionManager` defines transactional execution independently of a database. Supply an adapter implementing this
contract for your application's participating resources. For PDO persistence, register the
[PDO integration module](../integration/pdo/README.md) to provide a shared connection and transaction manager.

## Execute an operation

Inject `TransactionManager` into an application service and pass it a callable with no required arguments:

```php
// $transactionManager is your injected TransactionManager adapter.
// Both repositories must participate in the resources managed by that adapter.
$id = $transactionManager->transactional(function () use ($articles, $audit) {
    $article = $articles->create('A new article');
    $audit->recordArticleCreated($article->id());

    return $article->id();
});
```

The operation runs once after successful transaction startup. Its result is returned only after a successful commit.
The callable's result type is preserved through the contract's generic PHPDoc. Callbacks may also return nothing.

If the operation throws an exception or engine error, the manager attempts rollback. Successful rollback propagates the
original failure unchanged. If rollback also fails, `TransactionRollbackException` retains the operation failure as
`getPrevious()` and the rollback failure as `rollbackFailure`.

## Handle lifecycle failures

All transaction lifecycle exceptions implement `TransactionException`. Operation failures can belong to other
components or PHP itself and propagate unchanged after successful rollback.

| Exception | Meaning |
| --- | --- |
| `Exception\NestedTransactionException` | This manager already has an active transaction; the nested callback was not run. |
| `Exception\TransactionStartException` | Startup failed; the operation was not run. |
| `Exception\TransactionCommitException` | Commit was not confirmed; do not assume that changes were rolled back. |
| `Exception\TransactionRollbackException` | Both the operation and its rollback failed; both causes are retained. |

Adapters translate lower-level lifecycle failures into the corresponding exception and retain the original cause.
Commit failures can leave the outcome uncertain. Retry behavior must account for the adapter's guarantees and the
operation's idempotency; this component does not retry operations automatically.

## Nesting and participating resources

Nested `transactional()` calls on the same manager are rejected before their callback runs. The rejection itself leaves
the enclosing transaction untouched. If caught within the outer callback, the outer operation can continue; if it
escapes, the outer manager applies its normal rollback behavior. There are no implicit nested commits or savepoints.

Use one transaction boundary around an application operation. Helpers called within it should use the participating
repositories directly rather than starting another transaction. Resources managed by a different manager are not
covered by this transaction. Files, logs, network requests, and other side effects are covered only if they explicitly
participate in the adapter's transactional resources.

## Wrap CQRS commands

`Integration\Transaction\Middleware\TransactionalCommandMiddleware` wraps the remaining command pipeline:

```php
use ExtendsSoftware\ExaPHP\Cqrs\Command\SynchronousCommandBus;
use ExtendsSoftware\ExaPHP\Integration\Transaction\Middleware\TransactionalCommandMiddleware;

// $transactionManager is your adapter; $handlers contains your command-to-handler registrations.
$bus = new SynchronousCommandBus($handlers, [
    new TransactionalCommandMiddleware($transactionManager),
]);
```

The command and `DispatchContext` pass through unchanged. The middleware delegates transaction management to the
injected adapter and propagates its failures unchanged. Middleware before this step runs outside the transaction;
middleware after it and the handler run inside it. Place authorization before this step if it should finish before
transaction startup.

For application service wiring, register the middleware as a service using your adapter and add its service identifier
to `cqrs.command.middleware`, following the [CQRS integration guide](../integration/README.md). Transaction middleware
is opt-in and is not registered by `CqrsModule`. Queries are not wrapped automatically. Dispatching another command
through the same transactional middleware while a transaction is active triggers the nesting rejection.

The middleware does not dispatch domain events or schedule after-commit callbacks. Handlers dispatch recorded domain
events synchronously after saving aggregates and before this middleware commits. Listener failures must propagate so
the transaction can roll back, and all listener persistence must participate in the same transaction. Route external
side effects and deferred work through an application-provided transactional outbox. See the
[DDD guide](../ddd/README.md#save-and-dispatch-within-one-transaction) for dispatch and outbox guidance.
