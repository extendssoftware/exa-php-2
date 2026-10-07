# Messaging

The `ExtendsSoftware\ExaPHP\Messaging` component provides immutable JSON message envelopes and a durable publishing
contract, plus subscription definitions, lookup, subscriber processing, and consumer delivery coordination. Supply a
`MessagePublisher` implementation for your destination and inject it into application services.

## Create and publish a message

Choose a stable identifier, an application-defined message type, a JSON payload, and an explicit creation time:

```php
<?php

declare(strict_types=1);

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Messaging\Message;

use function json_encode;

use const JSON_THROW_ON_ERROR;

// $publisher is your MessagePublisher implementation.
$message = new Message(
    id: 'message-1',
    type: 'article.published.v1',
    payload: json_encode(['articleId' => 'article-1'], JSON_THROW_ON_ERROR),
    createdAt: new DateTimeImmutable('2026-10-07T12:00:00Z'),
);
$publisher->publish($message);
```

Identifiers and types must be non-empty. Their formats and payload schemas belong to the application. Payloads accept
any valid UTF-8 JSON value within validation depth 512. The envelope preserves supplied values, including JSON
formatting and timestamp timezone. Version message types when payload compatibility requires it. Reuse the same
identifier when attempting to publish the same message again.

## Register subscriptions

Define a stable subscriber identifier and the exact message types it subscribes to:

```php
use ExtendsSoftware\ExaPHP\Messaging\Subscription\RegisteredSubscriptions;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\Subscription;

$subscriptions = new RegisteredSubscriptions([
    new Subscription('search-index', ['article.published.v1', 'article.updated.v1']),
    new Subscription('notifications', ['article.published.v1']),
]);

// $message is a Message with type article.published.v1.
$matching = $subscriptions->matching($message); // search-index, then notifications
$all = $subscriptions->all();
```

Inject `Subscription\SubscriptionRegistry` into code that needs subscription lookup. `matching()` returns subscriptions
containing the message's exact, case-sensitive type, with each subscriber appearing at most once. No match returns an
empty list. `all()` lists every subscription. `RegisteredSubscriptions` preserves registration order and returns the
original immutable definitions from both methods.

A `Subscription` preserves `subscriberId` and `messageTypes` without normalization. The identifier must be non-empty,
and types must form a non-empty list of distinct, non-empty strings. Wildcard characters have no special meaning.
Choose subscriber identifiers that remain stable across deployments if they identify durable delivery records.

`RegisteredSubscriptions` accepts an empty list, but rejects malformed registrations and duplicate subscriber IDs,
even when their message types differ. Combine all types for one subscriber in a single definition. Different subscribers
may subscribe to the same type. Registration and lookup do not construct handlers or execute subscriber code.

An application publisher can use matching definitions to determine its delivery recipients. The registry performs
lookup only; the publisher adapter owns durable acceptance and duplicate handling described below.

## Implement a subscriber

Implement `Subscription\MessageSubscriber::handle(Message $message): void` in your application handler. Inject its
business dependencies through the constructor. Return after processing succeeds and let failures propagate to the
caller; application exceptions do not need to implement `MessagingException`.

Processing must tolerate duplicate delivery using the stable message identifier. Successful return does not acknowledge
a transport delivery. The calling worker owns acknowledgement and retry decisions and must not acknowledge a failed
handler invocation.

## Resolve subscribers

Inject `Subscription\SubscriberResolver` into code that needs to locate a subscriber by its stable ID. Subscription
lookup remains separate from resolution: `SubscriptionRegistry` describes recipients; the resolver supplies their
application handlers.

`Integration\Messaging\Resolver\ServiceLocatorSubscriberResolver` accepts an explicit subscriber-ID-to-service-ID
mapping. For example, assume your locator already registers `article-indexer` and `article-notifier` as services
implementing `MessageSubscriber`:

```php
use ExtendsSoftware\ExaPHP\Integration\Messaging\Resolver\ServiceLocatorSubscriberResolver;

// $services is the application's ServiceLocator; $message is the Message being processed.
$resolver = new ServiceLocatorSubscriberResolver($services, [
    'search-index' => 'article-indexer',
    'notifications' => 'article-notifier',
]);

$subscriber = $resolver->resolve('search-index');
$subscriber->handle($message);
```

Construction validates the mapping without resolving services. Resolution loads only the service mapped to the exact,
case-sensitive subscriber ID and checks that it implements `MessageSubscriber`. An unmapped ID fails even if a service
exists with the same name. Resolution does not call `handle()` or select recipients for a message.

Mappings may be empty, and multiple subscriber IDs may share one service. Subscriber and service IDs must be non-empty;
service IDs must be strings. Numeric subscriber IDs represented by integer PHP array keys are supported. Duplicate
array keys are overwritten by PHP before construction, so the resolver cannot detect them. Service lifetimes and
sharing follow the locator's configuration; the resolver adds no cache.

## Implement durable publishing

Implement `MessagePublisher::publish(Message $message): void`. Preserve all envelope fields and return only after the
destination acknowledges durable acceptance for distribution. Acceptance does not mean that subscribers have finished
processing. Database adapters must not return success while a write is still pending in an uncommitted transaction.

Translate expected publishing failures into `Exception\MessagePublishException`, adding useful context and retaining
lower-level causes as previous exceptions. A failure may occur after the destination accepted the message but before
confirmation reached the publisher. Repeated publication may therefore cause duplicate delivery. Design receiving
systems to handle duplicates using the stable message identifier; the contract provides no exactly-once or ordering
guarantee.

Integration-test an adapter's durable acceptance, preservation of envelope fields, ambiguous acknowledgement failures,
and error translation against its real destination. If publishing accompanies application changes that must commit
atomically, record an [outbox message](../outbox/README.md#append-within-the-command-transaction) in that transaction
and publish it asynchronously.

## Receive and settle subscriber deliveries

Implement `Consumption\MessageConsumer` in your transport adapter. The consumer acquires at most one delivery per
`receive()` call and returns `null` when none is available, rather than waiting indefinitely. It owns receiving,
acknowledgement, retry scheduling, and rejection; each operation affects one subscriber's delivery.

`Consumption\ReceivedDelivery` is an immutable value containing the original `message`, a non-empty `subscriberId`,
an opaque non-empty `receipt`, and a positive `attempt` number. The receipt identifies a specific attempt issued by that
consumer. Pass the value
back to the same consumer for settlement. Constructing this value only validates its fields; it does not acquire
ownership or make an invented receipt valid. The attempt starts at one for each message/subscriber pair and increases
on every acquisition, including recovery when subscriber processing never started. Adapters must preserve this count
across retries and recovery.

Use the processor below to resolve the subscriber, handle one message, and record the outcome.

The consumer exposes these operations:

- `receive()`: obtain a message, subscriber identity, and fresh receipt, or `null` when no delivery is available.
- `acknowledge($delivery)`: submit acknowledgement of successful processing.
- `retry($delivery, $availableAt)`: durably schedule this subscriber's message again, then settle the current attempt.
  Supply a `DateTimeImmutable` for the earliest retry time. A time at or before the consumer's current time permits
  immediate retry; a future time prevents earlier eligibility, while actual delivery may occur later.
- `reject($delivery)`: submit terminal rejection without scheduling another attempt. Retention or disposal follows the
  destination's configuration; rejection does not promise an inspectable failure record.

After a successful outcome, the receipt is invalid for further settlement. The consumer must reject unknown, foreign,
expired, invalidated, or already settled receipts and verify their associated message and subscriber identity. An old
receipt must never settle a newer attempt. Redelivery preserves the message and subscriber but receives a fresh receipt,
including after a reconnect or recovery. Other subscribers' deliveries remain independent.

Acknowledgement and rejection return after successful submission, not an exactly-once guarantee. Duplicates remain
possible, and failed settlement can have an unknown outcome. Follow the adapter's recovery behavior rather than assuming
another outcome is safe for the same receipt.

## Process one subscriber delivery

Compose `Consumption\MessageProcessor` with your consumer, subscriber resolver, retry policy, and clock:

```php
use ExtendsSoftware\ExaPHP\Clock\SystemClock;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\MessageProcessor;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry\FixedDelayRetryPolicy;

// $consumer implements MessageConsumer; $resolver implements SubscriberResolver.
$processor = new MessageProcessor(
    consumer: $consumer,
    resolver: $resolver,
    retryPolicy: new FixedDelayRetryPolicy(delaySeconds: 60, maxAttempts: 3),
    clock: new SystemClock(),
);
$processed = $processor->process();
```

Each call receives at most one delivery. It returns `false` for an empty poll, or `true` after successfully
acknowledging,
scheduling a retry, or rejecting a delivery. Thus `true` does not necessarily mean subscriber processing succeeded.
The processor does not start transactions, poll in a loop, or manage transport recovery.

All `Throwable` failures from `MessageSubscriber::handle()`, including engine errors such as `TypeError`, are passed
to the retry policy. Receiving and subscriber resolution happen outside this exception boundary. Acknowledgement also
happens outside it: failed acknowledgement never triggers retry evaluation. Resolution, policy, clock, and settlement
failures stop processing without attempting another outcome. The consumer adapter owns recovery of unsettled deliveries.

### Configure consumer retries

`Consumption\Retry\FixedDelayRetryPolicy` accepts non-negative delay seconds and a positive total attempt limit. The
example retries subscriber failures after 60 seconds and rejects on failure at attempt three or later. A limit of one
disables retries; zero delay allows immediate retry. Successful handling is acknowledged even if the attempt number is
above the limit, since the policy runs only after a subscriber failure.

Implement `Consumption\Retry\RetryPolicy::delay($delivery, $failure)` for application decisions. The policy receives the
whole `ReceivedDelivery` and the original subscriber `Throwable`. Return non-negative seconds to retry, or `null` to
reject. The processor reads its clock after the policy decision and calculates the absolute retry timestamp. No clock
read is needed for successful processing or rejection. Keep processor and consumer clocks consistent.

This policy is independent of Outbox retries: it governs subscriber execution rather than publication. Both policies
can therefore have different limits and application rules.

## Run the CLI consumer

Register `Integration\Messaging\MessagingModule` alongside `CliModule` and `ClockModule`. The module provides the
subscription registry, lazy subscriber resolver, processor, worker settings, and `messaging:consume` command.
Applications supply `MessageConsumer` and `Consumption\Retry\RetryPolicy` services. `ClockModule` supplies the clock;
you can instead register your own `Clock` service. Consumption does not require a publisher or Outbox services.

For example, place this configuration in `config/messaging.global.php`. `DatabaseConsumer` and `ArticleIndexer` below
are application classes implementing `MessageConsumer` and `MessageSubscriber`; register their dependencies as well.

```php
use App\Messaging\ArticleIndexer;
use App\Messaging\DatabaseConsumer;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\MessageConsumer;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry\FixedDelayRetryPolicy;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry\RetryPolicy;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\Subscription;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ReflectionDefinition;

return [
    'services' => [
        MessageConsumer::class => new ReflectionDefinition(DatabaseConsumer::class),
        RetryPolicy::class => new InstanceDefinition(new FixedDelayRetryPolicy(60, 3)),
        'article-indexer' => new ReflectionDefinition(ArticleIndexer::class),
    ],
    'messaging' => [
        'subscriptions' => ['index' => new Subscription('search-index', ['article.published.v1'])],
        'subscribers' => ['search-index' => 'article-indexer'],
        'worker' => ['idle_delay_seconds' => 1],
    ],
];
```

`subscriptions` contains named `Subscription` values; names identify configuration entries for merging, while each
value supplies the subscriber identity. `subscribers` maps subscriber IDs to service IDs. Both default to empty arrays
and are resolved independently: subscription lookup does not instantiate handlers. The positive integer
`worker.idle_delay_seconds` defaults to one and controls waiting after an empty poll. Configure transport ownership
and recovery settings on the consumer adapter.

Create a CLI entry point such as `bin/console`:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Cli\Output\StreamOutput;
use ExtendsSoftware\ExaPHP\Integration\Cli\CliModule;
use ExtendsSoftware\ExaPHP\Integration\Cli\ExceptionHandlingCliRunner;
use ExtendsSoftware\ExaPHP\Integration\Clock\ClockModule;
use ExtendsSoftware\ExaPHP\Integration\Messaging\MessagingModule;

require __DIR__ . '/../vendor/autoload.php';

$application = new Application(__DIR__ . '/../config');
$application->registerModule(CliModule::class);
$application->registerModule(ClockModule::class);
$application->registerModule(MessagingModule::class);
exit(new ExceptionHandlingCliRunner()->run($application, $argv, new StreamOutput(STDOUT, STDERR)));
```

```sh
php bin/console messaging:consume
php bin/console messaging:consume --once
php bin/console messaging:consume --help
```

Persistent execution initializes the application once, processes deliveries repeatedly, and waits only when no delivery
is available. It uses the shared [CLI worker control](../cli/README.md) to finish the current attempt after SIGTERM or
SIGINT, then stop receiving. The default control requires `ext-pcntl`. `--once` performs at most one receive attempt,
without signal setup or idle waiting, and also succeeds when no delivery is available. Help does not resolve the
consumer or subscriber services.

Successful acknowledgement, scheduled retry, and rejection all count as completed processing attempts. Unhandled
processing or lifecycle failures propagate to the CLI error boundary, which reports an error and returns a nonzero
exit code. Use a process supervisor to restart failed workers; unsettled delivery recovery belongs to the consumer.
The worker does not open transactions. Keep transactions scoped to the persistence or handoff operation that requires
atomicity.

Invalid module configuration raises `Integration\Messaging\Exception\InvalidMessagingConfigurationException`;
invalid subscriber mappings use `InvalidSubscriberMappingException`. Service construction failures are retained as
causes of service-locator resolution exceptions. If processing and signal cleanup both fail, `WorkerRunException`
preserves the processing failure as its previous exception and exposes `cleanupFailure`. These integration exceptions
implement `IntegrationException`.

## Implement a consumer adapter

A database consumer can associate receipts with claim tokens and validate ownership atomically when recording each
outcome. A broker consumer can associate receipts with its channel and acknowledgement information. Those details stay
inside the consumer; the received value carries no transport operations. Receipts must be unique to the issuing consumer
and attempt and must not be reused. A broker tag alone is insufficient if it can repeat on a new channel.

The shared API requires an acquisition count for each message/subscriber pair, but does not impose leases or database
transactions on every transport. Use a transport counter with matching guarantees or persist equivalent accounting;
a redelivery flag alone is insufficient. Preserve the count across reconnects and retry scheduling. Document the
adapter's ownership lifetime, reconnect behavior, and recovery of unsettled deliveries.

The caller calculates the retry timestamp using its retry decision and clock. A database adapter can store that instant;
a broker adapter can translate it into supported scheduling using its own clock. Compare timestamps as instants across
timezones and preserve the earliest eligibility guarantee when converting to transport-specific time precision.

Delayed retry requires durable scheduling support. An adapter must ensure the retry is accepted before relinquishing
the current delivery, preserving the message and subscriber identity. If it cannot support the requested retry time, it
must
raise `DeliverySettlementException` without relinquishing the message, rather than silently requeueing immediately.
The retry operation schedules work; it must not sleep. Duplicate protection remains necessary when scheduling and
settling cannot be performed atomically.

Integration-test real adapters for empty polling, independent subscriber deliveries, duplicate handling, repeated
settlement, stale and foreign receipts, retry-time boundaries, unsupported scheduling, and ambiguous outcomes. Verify
that
receipts cannot settle another message or subscriber and are not reused after reconnection. Test recovery after a worker
or connection stops, including failure between retry scheduling and settlement, and verify increasing attempt counts
after recovery. Expected transport failures must be
translated into the corresponding consumption exceptions with their causes preserved.

## Publish through Outbox

`Integration\Messaging\Outbox\PublishingMessageDelivery` implements Outbox's `MessageDelivery` using a Messaging
publisher. It converts each `OutboxMessage` to `Message`, preserving its identifier, type, JSON text, and timestamp.

```php
use ExtendsSoftware\ExaPHP\Clock\SystemClock;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Outbox\PublishingMessageDelivery;
use ExtendsSoftware\ExaPHP\Outbox\Processing\OutboxProcessor;
use ExtendsSoftware\ExaPHP\Outbox\Retry\FixedDelayRetryPolicy;

// $store is your OutboxStore adapter; $publisher is your MessagePublisher implementation.
$processor = new OutboxProcessor(
    store: $store,
    delivery: new PublishingMessageDelivery($publisher),
    retryPolicy: new FixedDelayRetryPolicy(60, 3),
    clock: new SystemClock(),
);
```

For the [Outbox CLI worker](../outbox/README.md#run-the-cli-worker), register your publisher as
`Messaging\MessagePublisher` and configure the delivery service using `ReflectionDefinition`:

```php
use ExtendsSoftware\ExaPHP\Integration\Messaging\Outbox\PublishingMessageDelivery;
use ExtendsSoftware\ExaPHP\Outbox\Delivery\MessageDelivery;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ReflectionDefinition;

return ['services' => [
    MessageDelivery::class => new ReflectionDefinition(PublishingMessageDelivery::class),
]];
```

After publishing returns, the processor separately completes the claim. If completion fails, the message may be
published again after claim expiry. The bridge does not combine publication and completion in a transaction.

## Handle failures

`MessagingException` is the component's root exception contract:

- `Exception\InvalidMessageException`: construction received an empty identifier or type, or invalid JSON.
- `Exception\MessagePublishException`: durable acceptance could not be acknowledged.

The Outbox bridge translates `MessagePublishException` into `Outbox\Delivery\Exception\MessageDeliveryException`,
including the outbox message identifier in its context and retaining the original exception. The processor then uses
its configured retry policy. Unexpected publisher exceptions and engine errors propagate unchanged without being
classified as retryable delivery failures.

Subscription failures also implement `MessagingException` and live under `Subscription\Exception`:

- `DuplicateSubscriptionException`: a subscriber identifier is registered more than once.
- `InvalidSubscriptionException`: a subscriber identifier or message-type list is invalid.
- `InvalidSubscriptionRegistrationException`: registrations are not a list of `Subscription` instances.
- `SubscriberResolutionException`: a subscriber is unmapped, service lookup fails, or the result is not a subscriber.
  Service-locator failures are retained as previous exceptions; unexpected exceptions and engine errors propagate.

`Integration\Messaging\Exception\InvalidSubscriberMappingException` implements `IntegrationException` and reports
invalid subscriber-to-service mappings at resolver construction.

Consumption failures live under `Consumption\Exception` and implement `MessagingException`:

- `DeliverySettlementException`: acknowledgement, retry scheduling, or rejection failed or has an uncertain outcome.
- `InvalidReceivedDeliveryException`: the subscriber identifier or receipt is empty, or the attempt is not positive.
- `MessageReceiveException`: acquiring a delivery failed.
- `RetryTimestampException`: obtaining the retry timestamp failed; the original `ClockException` is preserved.
- `UnavailableDeliveryException`: the receipt does not identify a valid outstanding attempt for the consumer.

`Consumption\Retry\Exception\InvalidRetryPolicyException` implements `MessagingException` and reports invalid
fixed-delay
configuration or a negative delay returned by a custom policy.
