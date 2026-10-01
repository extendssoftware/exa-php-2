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

- `CommandBus::dispatch(Command $command): void` sends a command to its handler without returning a result.
- `QueryBus::ask(Query $query): mixed` returns the result produced by the query's handler.

`ask()` declares `TResult` per call and connects its `Query<TResult>` parameter to its result. Generic-aware tooling can
infer `string|null` for `ask(new FindArticleTitle($id))` from the query example, even when the caller only knows
`QueryBus`.
This is static type information, not runtime result validation.

The contracts describe dispatch and results independently of handler lookup or storage. Dispatch failures use
`CqrsException`; exceptions and errors raised during handling propagate unchanged.

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

`dispatch()` invokes the handler before returning and passes the original command object. It returns no result.
Exceptions and errors thrown by the handler propagate unchanged, preserving domain-specific failure types.

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

`ask()` passes the original query object to its handler and returns the handler's result unchanged, including null.
Objects retain their identity. The method's `TResult` generic preserves the query-to-result relationship for tooling;
the bus does not validate result types at runtime. Handler exceptions and errors propagate unchanged.

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
domain exceptions remain independent of the framework; buses retain those exceptions and engine errors unchanged.
