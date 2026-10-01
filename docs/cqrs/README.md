# CQRS

The `ExtendsSoftware\ExaPHP\Cqrs` namespace provides command and query message, handler, and bus contracts, plus a root
exception contract. `SynchronousCommandBus` and `SynchronousQueryBus` invoke registered handlers immediately.
Applications define their own message classes and handlers.

## Define messages

Implement `Command` for a requested action and `Query<TResult>` for a request for information. Both interfaces have no
methods. Immutable DTOs containing identifiers and values are suitable message classes.

A command carries the input for an action without declaring a result type:

```php
<?php

declare(strict_types=1);

namespace App\Article;

use ExtendsSoftware\ExaPHP\Cqrs\Command\Command;

/**
 * Requests creation of an article.
 */
final readonly class CreateArticle implements Command
{
    /**
     * Creates an article command.
     *
     * @param string $id The article identifier.
     * @param string $title The article title.
     */
    public function __construct(public string $id, public string $title)
    {
    }
}
```

A query declares its expected result with `@implements Query<TResult>`. This relationship is available to generic-aware
IDEs and static analyzers; PHP does not enforce it at runtime:

```php
<?php

declare(strict_types=1);

namespace App\Article;

use ExtendsSoftware\ExaPHP\Cqrs\Query\Query;

/**
 * Requests an article title, or null when the article does not exist.
 *
 * @implements Query<string|null>
 */
final readonly class FindArticleTitle implements Query
{
    /**
     * Creates an article title query.
     *
     * @param string $id The article identifier.
     */
    public function __construct(public string $id)
    {
    }
}
```

## Handle commands

Implement `ExtendsSoftware\ExaPHP\Cqrs\Command\CommandHandler` to perform an action requested by a command object.
`handle(Command $command): void` returns no result. Use constructor injection for the handler's dependencies.

The `TCommand` template identifies the accepted command type. A handler for `CreateArticle` can declare
`@implements CommandHandler<CreateArticle>` in its class PHPDoc.

## Handle queries

Implement `ExtendsSoftware\ExaPHP\Cqrs\Query\QueryHandler` to answer a query without changing application state.
`handle(Query $query): mixed` returns the query result, which may be an object, array, scalar, or null.

The `TQuery` template is constrained to `Query<TResult>`, connecting the accepted query to the handler's result type.
A handler for the query above can declare `@implements QueryHandler<FindArticleTitle, string|null>`.

PHPDoc generics describe types for static analysis; they do not enforce message types at runtime. Implementations must
accept the native `Command` or `Query` parameter type, or a broader type such as `object`, rather than narrowing it to a
specific message class. Query handlers may use a more specific compatible native return type. Document the refined
parameter and result types on each implementation.

## Dispatch through buses

Depend on `ExtendsSoftware\ExaPHP\Cqrs\Command\CommandBus` to dispatch commands and
`ExtendsSoftware\ExaPHP\Cqrs\Query\QueryBus` to request query results. Inject implementations through constructors.

- `CommandBus::dispatch()` accepts a command and optional dispatch context, without returning a result.
- `QueryBus::ask()` accepts a query and optional dispatch context, returning the query result.

`ask()` declares `TResult` per call and connects its `Query<TResult>` parameter to its result. Generic-aware tooling can
infer `string|null` for `ask(new FindArticleTitle($id))` from the query example, even when the caller only knows
`QueryBus`.
This is static type information, not runtime result validation.

The contracts describe dispatch and results independently of handler lookup or storage. Dispatch failures use
`CqrsException`; unhandled exceptions and errors propagate unchanged. Middleware may intercept failures.

## Register and dispatch commands synchronously

Construct `SynchronousCommandBus` with a map from concrete command class names to handler instances. For the
`CreateArticle` message above, assume your application provides an autoloadable `CreateArticleHandler` implementing
`CommandHandler<CreateArticle>`, with its required dependencies already injected into `$createArticleHandler`:

```php
<?php

declare(strict_types=1);

use App\Article\CreateArticle;
use ExtendsSoftware\ExaPHP\Cqrs\Command\SynchronousCommandBus;

$bus = new SynchronousCommandBus([
    CreateArticle::class => $createArticleHandler,
]);

$bus->dispatch(new CreateArticle('article-1', 'Hello world'));
```

`dispatch()` runs the middleware chain and then invokes the handler before returning. It returns no result.
Without middleware, the original command is passed directly to its handler and failures propagate unchanged.

## Register and answer queries synchronously

Construct `SynchronousQueryBus` with query class names mapped to handler instances. For `FindArticleTitle` above,
assume `$findArticleTitleHandler` implements `QueryHandler<FindArticleTitle, string|null>` and has its dependencies
already injected:

```php
<?php

declare(strict_types=1);

use App\Article\FindArticleTitle;
use ExtendsSoftware\ExaPHP\Cqrs\Query\SynchronousQueryBus;

$bus = new SynchronousQueryBus([
    FindArticleTitle::class => $findArticleTitleHandler,
]);

$title = $bus->ask(new FindArticleTitle('article-1'));
```

`ask()` runs its middleware chain before invoking the handler. Without middleware, it passes the original query object
and returns the handler's result unchanged, including null. Objects retain their identity. The method's `TResult`
generic
preserves the query-to-result relationship for tooling; the bus does not validate result types at runtime.

## Synchronous registration rules

Both synchronous buses fix registrations at construction and reuse the supplied handler instances. An empty map is
allowed. Supply instances with their dependencies already constructed; the buses do not resolve services or instantiate
handlers. Application factories can assemble these maps and expose the buses under the `CommandBus` and `QueryBus`
service identifiers.

Routing uses the message's exact class; a handler registered for a parent class does not handle subclasses. Command
registrations require concrete classes implementing `Command` and instances implementing `CommandHandler`. Query
registrations require concrete classes implementing `Query` and instances implementing `QueryHandler`.
PHPDoc generic bindings are not checked at runtime: ensure each handler accepts the message class it is registered for.

Class aliases and differently cased class names are normalized. Multiple keys referring to the same class are rejected.
Identical array keys are overwritten by PHP before the constructor receives the map, so they cannot be detected as
separate registrations.

The following exceptions live in `ExtendsSoftware\ExaPHP\Cqrs\Exception` and implement `CqrsException`:

| Failure | Command bus | Query bus |
| --- | --- | --- |
| Invalid message class or handler | `InvalidCommandRegistrationException` | `InvalidQueryRegistrationException` |
| Multiple keys identify the same class | `DuplicateCommandHandlerException` | `DuplicateQueryHandlerException` |
| No handler for the exact message class | `CommandHandlerNotFoundException` | `QueryHandlerNotFoundException` |

Class inspection failures preserve their original exception or error as the previous exception of the invalid
registration exception.

## Exception contract

`CqrsException` extends `Throwable` and identifies command and query dispatch failures. Catch this interface to handle
CQRS component failures together.

Component-specific exception classes must implement `CqrsException` and extend an appropriate SPL exception class.
Application-domain exceptions do not need to implement this contract merely because they occur in a command or query.
Handler contracts allow exceptions and errors to propagate; they do not require translating domain failures into CQRS
exceptions.
Concrete handlers should document their specific relevant exceptions. The shared handler contracts use `Throwable` so
domain exceptions remain independent of the framework. Unhandled failures propagate unchanged; middleware
may intercept them.

## Command middleware

Implement `Cqrs\Command\Middleware\CommandMiddleware` to wrap command execution. Middleware receives the command,
an immutable `DispatchContext`, and a `CommandExecution` representing the rest of the chain. Call
`$next->execute($command, $context)` to continue, or return without calling it to short-circuit. Calling it more than
once
executes the remaining chain more than once; the bus does not enforce single execution.

Pass an ordered list of middleware instances as the second argument to `SynchronousCommandBus`. The first entry is
outermost: its code before `$next` runs first, and its code after `$next` runs last. Handler lookup occurs at the end of
the chain, so middleware can short-circuit even a command without a registered handler. The list is validated at
construction; invalid entries or non-list keys raise `InvalidCommandMiddlewareException`.

For example, this middleware measures downstream execution. The injected callback receives elapsed seconds even if
execution throws:

```php
<?php

declare(strict_types=1);

namespace App\Middleware;

use Closure;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Command;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Middleware\CommandExecution;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Middleware\CommandMiddleware;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;
use Throwable;

use function hrtime;

/**
 * Measures command execution time.
 */
final readonly class MeasureCommand implements CommandMiddleware
{
    /**
     * Creates timing middleware.
     *
     * @param Closure(float): void $record The timing recorder.
     */
    public function __construct(private Closure $record)
    {
    }

    /**
     * Measures execution of the remaining chain.
     *
     * @param Command $command The command to execute.
     * @param DispatchContext $context The execution metadata.
     * @param CommandExecution $next The remaining chain.
     *
     * @return void
     *
     * @throws Throwable When downstream execution or timing recording fails.
     */
    public function process(Command $command, DispatchContext $context, CommandExecution $next): void
    {
        $start = hrtime(true);
        try {
            $next->execute($command, $context);
        } finally {
            ($this->record)((hrtime(true) - $start) / 1_000_000_000);
        }
    }
}
```

Middleware may forward a replacement command or a new context. Handlers retain `handle(Command): void` and receive no
context. Middleware and handlers are reused across calls; keep per-dispatch state in local variables or context.
Nested dispatch starts a new chain and does not inherit context unless the caller passes it explicitly. Unhandled
middleware and handler exceptions propagate unchanged; middleware can deliberately catch or translate downstream errors.

## Query middleware

Implement `Cqrs\Query\Middleware\QueryMiddleware` to wrap query execution. Its `process()` method receives a query,
the shared `DispatchContext`, and a `QueryExecution` continuation. Return `$next->execute($query, $context)` to continue
and preserve the downstream result. Middleware can return its own result without invoking the continuation, for example
on a cache hit. It must return the result type declared by the query, including when replacing or transforming a result.

Both `process()` and `execute()` declare `TResult` per call, connecting `Query<TResult>` to the return value. A
middleware
implementation should repeat those method PHPDoc generics. Native return types remain `mixed`; result types are not
validated at runtime. For example, inside a middleware with an injected application authorization service:

```php
/**
 * Authorizes a query before executing it.
 *
 * @template TResult
 *
 * @param Query<TResult> $query The query to execute.
 * @param DispatchContext $context The execution metadata.
 * @param QueryExecution $next The remaining pipeline.
 *
 * @return TResult The query result.
 *
 * @throws Throwable When authorization or query execution fails.
 */
public function process(Query $query, DispatchContext $context, QueryExecution $next): mixed
{
    $this->authorization->assertAllowed($query, $context);

    return $next->execute($query, $context);
}
```

Import `Cqrs\Query\Query`, `Cqrs\DispatchContext`, `Cqrs\Query\Middleware\QueryExecution`, and `Throwable` in the
implementation. The application defines the authorization service and its policy; the framework supplies no user model.

Pass middleware instances as the second constructor argument to `SynchronousQueryBus`. The first entry is outermost;
handler lookup occurs only if execution reaches the end of the chain. Invalid lists or entries raise
`InvalidQueryMiddlewareException`. Calling the continuation more than once repeats downstream execution.

Query middleware follows the command pipeline's context and exception behavior: each call starts a fresh execution,
objects are reused, and nested calls inherit no context implicitly. Middleware can forward a new context or a
replacement
query with a compatible result type. Unhandled exceptions and errors propagate unchanged. Handlers still receive only
the query through `handle(Query): mixed`.

## Dispatch metadata

`DispatchContext` stores application-defined objects indexed by their exact concrete class names. There is no framework
user or actor model. For an application-defined immutable `ActorContext`, an entry point can call:

```php
$context = new DispatchContext([new ActorContext($actorId)]);
$bus->dispatch(new PublishArticle($articleId), $context);
```

The example assumes these application classes and the bus are in scope. Middleware can use
`$context->get(ActorContext::class)` to retrieve metadata with generic type inference, or `has()` to check availability.
Missing metadata raises `DispatchMetadataNotFoundException`. Constructor entries must be objects with unique concrete
classes; invalid or duplicate entries raise `InvalidDispatchMetadataException`. Input array keys are ignored. Lookups
use the exact class name, without parent/interface, alias, or case normalization.

`$context->with($metadata)` returns a new context, adding or replacing that concrete class and leaving the original
unchanged. Stored objects are not cloned or made immutable; prefer immutable metadata objects. Omitted contexts default
to a fresh empty context on each call. Middleware decides which metadata it requires; missing identity grants no
implicit access.

Custom `CommandBus` and `QueryBus` implementations must accept the optional `DispatchContext` argument. Existing
one-argument calls remain supported.
