# Event

The `ExtendsSoftware\ExaPHP\Event` namespace provides event, listener, and dispatcher contracts. Events describe
something that has happened and can have zero or more interested listeners.

## Define an event

Implement the `Event` marker interface on an application event. Immutable objects are suitable for carrying event data:

```php
<?php

declare(strict_types=1);

namespace App\Article;

use ExtendsSoftware\ExaPHP\Event\Event;

/**
 * Records that an article was created.
 */
final readonly class ArticleCreated implements Event
{
    /**
     * Creates the event.
     *
     * @param string $articleId The created article's identifier.
     */
    public function __construct(public string $articleId)
    {
    }
}
```

## Handle events

Implement `ExtendsSoftware\ExaPHP\Event\Listener\EventListener` and its `handle(Event $event): void` method.
Declare `@implements EventListener<ArticleCreated>` on a listener for the event above and document its method parameter
as `@param ArticleCreated $event`. Inject any services needed by the listener through its constructor.

The `TEvent` generic supports IDEs and static analysis; PHP does not enforce it at runtime. The native method parameter
must accept `Event` or a broader compatible type, rather than narrowing it to `ArticleCreated`.

## Dispatch events

Inject `EventDispatcher` into the application service that publishes events and call
`$dispatcher->dispatch(new ArticleCreated($articleId))`. Dispatch returns no result, and an event without listeners
is valid. The publishing service does not need to know individual listeners.

## Register listeners for synchronous dispatch

Construct `SynchronousEventDispatcher` with event class names mapped to ordered lists of listener instances.
For the event above, assume `$updateIndex` and `$recordActivity` implement `EventListener<ArticleCreated>` and already
have their dependencies injected:

```php
<?php

declare(strict_types=1);

use App\Article\ArticleCreated;
use ExtendsSoftware\ExaPHP\Event\SynchronousEventDispatcher;

$dispatcher = new SynchronousEventDispatcher([
    ArticleCreated::class => [$updateIndex, $recordActivity],
]);

$dispatcher->dispatch(new ArticleCreated('article-1'));
```

Listeners run synchronously in list order, each receiving the original event object. Routing uses the exact event class;
parent-class and interface registrations do not provide fallback routing. Events without listeners are ignored.

Registrations are fixed at construction, and listener instances are reused. Each map key must name a concrete class
implementing `Event`; each value must be a list of `EventListener` instances with consecutive integer keys starting at
zero. Empty maps and empty lists are valid. Repeating a listener in a list invokes it once for each occurrence.
The dispatcher does not construct or resolve listeners and does not validate PHPDoc generic bindings.

Class aliases and differently cased names are normalized. Separate keys identifying the same event class are rejected,
even when their lists are empty. Identical PHP array keys are overwritten before reaching the constructor and cannot be
detected as duplicate registrations.

Constructor failures use these exceptions in `ExtendsSoftware\ExaPHP\Event\Exception`, both implementing
`EventException`:

- `InvalidEventRegistrationException`: an event class, listener list, or listener is invalid. Class inspection failures
  retain the original exception or error as the previous exception.
- `DuplicateEventRegistrationException`: multiple keys identify the same canonical event class.

## Handle failures

`EventException` extends `Throwable` and identifies component dispatch failures. Application-domain exceptions do not
need to implement it. Listener exceptions and errors propagate unchanged and stop subsequent listeners for that
dispatch.
Dispatch does not guarantee rollback of work already performed by listeners.

Document specific failures on concrete listeners so callers can handle relevant application errors.

To collect listeners from application modules, use the
[Event integration module](../integration/README.md#register-the-event-module).
