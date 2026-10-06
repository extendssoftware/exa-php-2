# Outbox

The `ExtendsSoftware\ExaPHP\Outbox` component defines immutable outgoing messages and the `OutboxWriter` contract for
appending them within an application's transaction. Supply a persistence adapter implementing that contract and sharing
the transaction used by the related application repositories.

## Create a message

Supply a stable message identifier, an application-defined type, a JSON payload, and an explicit creation time:

```php
<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Outbox\OutboxMessage;

use function json_encode;

use const JSON_THROW_ON_ERROR;

$message = new OutboxMessage(
    id: 'message-1',
    type: 'article.announcement.requested.v1',
    payload: json_encode(['articleId' => 'article-1', 'title' => 'Hello'], JSON_THROW_ON_ERROR),
    createdAt: new DateTimeImmutable('2026-10-06T12:00:00Z'),
);
```

Identifiers must be unique within the outbox. Both the identifier and type must be non-empty; their format is otherwise
application-defined. Version message types when payload compatibility requires it. The envelope preserves the supplied
JSON text and timestamp without normalization. Payloads accept any valid UTF-8 JSON value within validation depth 512;
the application owns the payload schema and its interpretation.

## Append within the command transaction

Inject `OutboxWriter` into a synchronous domain-event listener or into a command handler whose explicit purpose is to
schedule deferred work. Convert domain events into messages in the application, selecting the data the consumer needs.
See the [DDD guide](../ddd/README.md#defer-work-through-a-transactional-outbox) for the listener flow.

Call `$writer->append($message)` while the transaction is active. The writer must participate in the same transaction
as the aggregate repository and must not start, commit, or roll back a transaction itself. Successful append leaves the
write pending: commit makes it available for processing, while rollback discards it. Appending does not deliver
messages.

Let write failures propagate to the [transaction middleware](../transaction/README.md#wrap-cqrs-commands) so the related
application changes roll back too. Merely using the same database with separate transactions does not provide atomicity.

## Handle failures

`OutboxException` is the component's root exception contract. Invalid envelope data raises
`Exception\InvalidOutboxMessageException` during construction. An adapter must throw `Exception\OutboxWriteException`
when no transaction is active, a message identifier already exists, or persistence fails. Duplicate writes must not
replace existing messages. Persistence failures must retain their lower-level cause as the previous exception.

When implementing an adapter, integration-test commit and rollback with application writes, rejection of writes
outside a transaction, duplicate identifiers, and visibility to a separate processing connection only after commit.
