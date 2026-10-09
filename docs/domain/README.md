# DDD

The `ExtendsSoftware\ExaPHP\Ddd` component supports aggregate roots that record domain events. Domain objects record
facts; application services control persistence and publication. The component depends on Event for the `DomainEvent`
marker contract and does not resolve services or dispatch events itself.

## Define a domain event

Implement `Ddd\Event\DomainEvent` for an immutable fact. It extends `Event`, so domain events can be passed directly to
`EventDispatcher`. For example, place this class in your application's `ArticleCreated.php`:

```php
<?php

declare(strict_types=1);

namespace App\Article;

use ExtendsSoftware\ExaPHP\Domain\Event\DomainEvent;

/**
 * Records that an article was created.
 */
final readonly class ArticleCreated implements DomainEvent
{
    /**
     * Creates the event.
     *
     * @param string $articleId The article identifier.
     * @param string $title The title at creation time.
     */
    public function __construct(public string $articleId, public string $title)
    {
    }
}
```

## Record events in an aggregate

Extend `Ddd\Aggregate\AbstractAggregateRoot` to record events through the protected `recordEvent()` method.
The base class implements `AggregateRoot` and provides a final `releaseEvents()` method. Subclasses need no parent
constructor call. In `Article.php`:

```php
<?php

declare(strict_types=1);

namespace App\Article;

use ExtendsSoftware\ExaPHP\Domain\Aggregate\AbstractAggregateRoot;

/**
 * Represents an article and records its creation.
 */
final class Article extends AbstractAggregateRoot
{
    /**
     * Initializes article state without recording an event.
     *
     * @param string $id The article identifier.
     * @param string $title The article title.
     */
    private function __construct(public readonly string $id, public readonly string $title)
    {
    }

    /**
     * Creates an article and records its creation.
     *
     * @param string $id The article identifier.
     * @param string $title The article title.
     *
     * @return self The new article.
     */
    public static function create(string $id, string $title): self
    {
        $article = new self($id, $title);
        $article->recordEvent(new ArticleCreated($id, $title));

        return $article;
    }

    /**
     * Restores a persisted article without recording creation again.
     *
     * @param string $id The persisted article identifier.
     * @param string $title The persisted title.
     *
     * @return self The restored article.
     */
    public static function restore(string $id, string $title): self
    {
        return new self($id, $title);
    }
}
```

`releaseEvents()` returns an empty list when nothing is pending. It drains the collection: calling it again returns
nothing unless new events were recorded. Event object identity and order are preserved, including repeated occurrences
of the same object. Releasing does not publish events, and later recording does not change a previously released list.

The abstract root delegates to a lazily initialized `RecordedEvents` collection. Cloning an aggregate copies its pending
collection while retaining event object identity. Recording or releasing on one copy does not affect the other.
Subclasses overriding `__clone()` must call `parent::__clone()` to preserve that independence.

For composition instead of inheritance, implement `AggregateRoot` directly, keep a private `RecordedEvents` instance,
and delegate `releaseEvents()` to its `release()` method. Domain behavior calls the collection's `record()` method.
Use a separate collection for each aggregate. Restoring persisted state should not record historical actions as new
pending events. This example's `restore()` method makes that distinction explicit.

## Save and dispatch within one transaction

Treat a command and its synchronous domain-event listeners as one atomic operation. Configure
[transactional command middleware](../transaction/README.md#wrap-cqrs-commands) with an application transaction adapter
and ensure the aggregate repository and all listener persistence participate in that transaction. Inside an application's
`CreateArticle` command handler, save the aggregate and dispatch its events before the middleware commits:

```php
$article = Article::create($command->id, $command->title);
$this->articles->save($article);

foreach ($article->releaseEvents() as $event) {
    $this->events->dispatch($event);
}
```

Here `Article` is the class above, `$command` supplies the identifier and title, `$this->articles` is an injected
application-specific repository, and `$this->events` is an injected `EventDispatcher`. Neither dependency belongs in the
aggregate. The repository saves article state without draining or persisting the in-memory event collection. Handlers
and synchronous listeners use the enclosing transaction directly; they must not start nested transactions.

If saving fails, this flow does not release or dispatch events. A synchronous listener failure stops dispatch and must
propagate to the transaction middleware, which attempts rollback of all participating changes. For example, a listener
can write required article history so the article and its history commit together. See
[transaction lifecycle failures](../transaction/README.md#handle-lifecycle-failures) for rollback and commit failures.

After release, the caller owns the pending batch; the aggregate does not retain or restore it when dispatch fails.
Database rollback also does not restore PHP object state. Discard affected aggregates after a failed operation and
reload persisted state before retrying.

Register listeners through the [Event integration module](../integration/README.md#register-the-event-module).
The dispatcher matches concrete event classes, so register `ArticleCreated::class` rather than `DomainEvent::class`.

## Defer work through a transactional outbox

Route external side effects and work whose failure should not reject the command through an application-provided
transactional outbox. A synchronous listener can translate a domain event into an outgoing message and persist it in
the same transaction as the aggregate. Failure to write that message must propagate and trigger rollback as well.

A separate application worker processes only committed messages, with retries and duplicate-delivery handling.
For example, a notification listener writes a message during article creation; the worker sends the notification later.
Delivery failure leaves the original command committed. Use stable message identifiers and idempotent consumers where
possible, since a worker can complete delivery and fail before recording success.

## Compose domain specifications

Implement `Ddd\Specification\Specification<T>` to test a domain condition without changing the candidate. Extend
`AbstractSpecification<T>` when fluent `and()`, `or()`, and `not()` composition is useful. Concrete business rules
belong
in your application. For the article example above:

```php
<?php

declare(strict_types=1);

namespace App\Article;

use ExtendsSoftware\ExaPHP\Domain\Specification\AbstractSpecification;

/**
 * Checks that an article has a non-empty title.
 *
 * @extends AbstractSpecification<Article>
 */
final class HasTitle extends AbstractSpecification
{
    /**
     * Checks the article title.
     *
     * @param Article $candidate The article to evaluate.
     *
     * @return bool Whether the title is non-empty.
     */
    public function isSatisfiedBy(object $candidate): bool
    {
        return $candidate->title !== '';
    }
}
```

Usage with the application classes above:

```php
$hasTitle = new HasTitle();
$untitled = $hasTitle->not();
$article = Article::create('article-1', 'Hello');

$hasTitle->isSatisfiedBy($article); // true
$untitled->isSatisfiedBy($article); // false
```

`$first->and($second)` requires both rules to pass; `$first->or($second)` requires either to pass. Composing creates new
specifications without evaluating candidates or modifying the original specifications. Operands are retained by
reference,
so keep rule inputs immutable when stable results are required. Inject time cutoffs explicitly rather than reading the
clock during evaluation.

`AndSpecification`, `OrSpecification`, and `NotSpecification` can also be constructed directly with implementations of
`Specification`, without requiring those implementations to extend the abstract class. Composite specifications support
further fluent composition. Chaining nests expressions left to right: `$a->or($b)->and($c)` means `(a OR b) AND c`.
Use `$a->or($b->and($c))` for `a OR (b AND c)`.

AND and OR evaluate the left operand first and skip the right operand once the result is determined. NOT evaluates its
operand once. Every evaluated operand receives the original candidate. Exceptions and errors propagate unchanged.
The `T` generic describes the candidate type for tooling; PHP still accepts any object, so callers must supply a
candidate
of the supported domain type. Combine rules for the same candidate type.

Specifications evaluate objects in memory; they do not translate into database queries or enforce aggregate invariants.
Domain operations must still enforce their business rules when called.

## Exceptions

`DomainException` extends `Throwable` as the component's root exception contract. Recording and releasing valid domain
events have no component-specific failure conditions. Business-rule exceptions belong to the application's domain;
they do not need to implement `DomainException`.
