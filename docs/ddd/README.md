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

use ExtendsSoftware\ExaPHP\Ddd\Event\DomainEvent;

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

use ExtendsSoftware\ExaPHP\Ddd\Aggregate\AbstractAggregateRoot;

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

## Persist before publishing

In an application's `CreateArticle` command handler, the flow can be:

```php
$article = Article::create($command->id, $command->title);
$this->articles->save($article);

foreach ($article->releaseEvents() as $event) {
    $this->events->dispatch($event);
}
```

Here `Article` is the class above, `$command` supplies the identifier and title, `$this->articles` is an injected
application-specific repository, and `$this->events` is an injected `EventDispatcher`. Neither dependency belongs in the
aggregate. The repository saves article state without draining or persisting the in-memory event collection. If an
explicit transaction is used, commit it before publishing.

If saving fails, this flow does not release or publish events. After release, the caller owns the pending batch; if
publication fails partway through, the aggregate does not retain or restore the events. Synchronous publication after
saving is not atomic with persistence and does not guarantee delivery. Durable delivery requires an application-level
strategy such as a transactional outbox.

Register listeners through the [Event integration module](../integration/README.md#register-the-event-module).
The dispatcher matches concrete event classes, so register `ArticleCreated::class` rather than `DomainEvent::class`.

## Exceptions

`DddException` extends `Throwable` as the component's root exception contract. Recording and releasing valid domain
events have no component-specific failure conditions. Business-rule exceptions belong to the application's domain;
they do not need to implement `DddException`.
