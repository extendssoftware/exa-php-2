# Outbox

The `ExtendsSoftware\ExaPHP\Outbox` component defines immutable outgoing messages, transactional writing, processing
ownership contracts, and delivery coordination. Supply persistence adapters for `OutboxWriter` and
`Processing\OutboxStore`. The writer must share its transaction with the related application repositories; the store
performs independent atomic processing operations.

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

## Process one message

Supply an `OutboxStore` adapter and a `Delivery\MessageDelivery` implementation, then compose a processor:

```php
use DateInterval;
use ExtendsSoftware\ExaPHP\Clock\SystemClock;
use ExtendsSoftware\ExaPHP\Outbox\Processing\OutboxProcessor;
use ExtendsSoftware\ExaPHP\Outbox\Retry\FixedDelayRetryPolicy;

// $store and $delivery are application-provided adapters.
$processor = new OutboxProcessor(
    store: $store,
    delivery: $delivery,
    retryPolicy: new FixedDelayRetryPolicy(delaySeconds: 60, maxAttempts: 3),
    clock: new SystemClock(),
);

$processed = $processor->process(new DateInterval('PT30S'));
```

Each invocation claims at most one message and makes one delivery attempt. It returns `false` when no message is
eligible, or `true` after successfully recording completion, a scheduled retry, or terminal failure. A `true` result
therefore does not necessarily mean successful delivery. Invoke the processor outside application transactions;
the application controls polling and worker lifetime, directly or through the [CLI worker](#run-the-cli-worker).
The processor does not sleep or renew claims.

### Provide delivery

Implement `Delivery\MessageDelivery::deliver()`. Return when the destination acknowledges acceptance and preserve the
message identifier across attempts. Translate expected delivery failures, including timeouts, into
`Delivery\Exception\MessageDeliveryException`, preserving any underlying exception as its previous cause. The
processor evaluates only this exception for retry; other exceptions and engine errors propagate unchanged.

Configure the delivery timeout to leave enough lease time for recording an outcome. Follow the
[ownership and duplicate-delivery constraints](#record-a-processing-outcome), including idempotency at the destination.

### Configure retries

`Retry\FixedDelayRetryPolicy` uses a non-negative delay in seconds and a positive total attempt limit. The example
schedules another attempt 60 seconds after the processor reads its clock following a delivery failure. A failure on
claim three or later records terminal failure; zero delay permits immediate retry, and a limit of one disables retries.
The limit is evaluated after delivery fails, so a reclaimed message may still deliver successfully at a higher claim
number. The count includes claims where a worker stopped before delivering.

Implement `Retry\RetryPolicy::delay($claim, $failure)` for other policies, such as exponential backoff or decisions
based on the delivery failure. The claim provides the attempt number and original message, allowing policies to use
message type or creation time. Return a non-negative number of seconds to retry, or `null` to record terminal failure.
The processor reads its injected clock only when scheduling a retry; the store independently evaluates ownership using
its own clock. Configure both clocks consistently.

### Handle processing failures

Store failures, including lost ownership, propagate without attempting another outcome. Completion happens outside the
delivery failure handler: failure to record successful delivery never triggers the retry policy. If policy evaluation
or reading the retry clock fails, no outcome is recorded and the claim remains subject to expiry.

`Retry\Exception\InvalidRetryPolicyException` reports invalid fixed-delay configuration and a
negative delay returned by a custom policy. `Processing\Exception\RetryTimestampException` wraps `ClockException`
when retry scheduling cannot obtain the current time. These and `MessageDeliveryException` implement `OutboxException`.

## Run the CLI worker

Register `Integration\Cli\CliModule`, `Integration\Clock\ClockModule`, and `Integration\Outbox\OutboxModule` in your
CLI application. Supply services for `OutboxStore`, `Delivery\MessageDelivery`, and `Retry\RetryPolicy` through your
application configuration. You may provide `Clock\Clock` yourself instead of registering `ClockModule`.

For example, assuming your application provides `App\Messaging\DatabaseOutboxStore` and
`App\Messaging\HttpMessageDelivery` with resolvable constructor dependencies, add `config/outbox.global.php`:

```php
<?php

declare(strict_types=1);

use App\Messaging\DatabaseOutboxStore;
use App\Messaging\HttpMessageDelivery;
use ExtendsSoftware\ExaPHP\Outbox\Delivery\MessageDelivery;
use ExtendsSoftware\ExaPHP\Outbox\Processing\OutboxStore;
use ExtendsSoftware\ExaPHP\Outbox\Retry\FixedDelayRetryPolicy;
use ExtendsSoftware\ExaPHP\Outbox\Retry\RetryPolicy;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ReflectionDefinition;

return [
    'services' => [
        OutboxStore::class => new ReflectionDefinition(DatabaseOutboxStore::class),
        MessageDelivery::class => new ReflectionDefinition(HttpMessageDelivery::class),
        RetryPolicy::class => new InstanceDefinition(new FixedDelayRetryPolicy(60, 3)),
    ],
    'outbox' => ['worker' => [
        'lease_seconds' => 30,
        'idle_delay_seconds' => 1,
    ]],
];
```

Both durations require positive integers in seconds; the values above are the module defaults. Choose the lease to
cover delivery and acknowledgement, and configure timeouts on external requests. The worker does not renew claims.

Use an entry point such as `bin/cli.php` with the existing CLI exception boundary:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Cli\Output\StreamOutput;
use ExtendsSoftware\ExaPHP\Integration\Cli\CliModule;
use ExtendsSoftware\ExaPHP\Integration\Cli\ExceptionHandlingCliRunner;
use ExtendsSoftware\ExaPHP\Integration\Clock\ClockModule;
use ExtendsSoftware\ExaPHP\Integration\Outbox\OutboxModule;

require __DIR__ . '/../vendor/autoload.php';

$application = new Application(__DIR__ . '/../config');
$application->registerModule(CliModule::class);
$application->registerModule(ClockModule::class);
$application->registerModule(OutboxModule::class);

exit(new ExceptionHandlingCliRunner()->run($application, $argv, new StreamOutput(STDOUT, STDERR)));
```

Run continuously or perform one poll:

```sh
php bin/cli.php outbox:work
php bin/cli.php outbox:work --once
php bin/cli.php outbox:work --help
```

The application bootstraps once and reuses its services for the lifetime of the command. The worker immediately polls
again after a recorded outcome and waits only when no message is eligible. `--once` attempts at most one message,
returns successfully even when the queue is empty, and never waits. A scheduled retry or recorded terminal failure is
also a successfully handled attempt; it does not make the command fail.

The default persistent worker requires `ext-pcntl`, enabled in the development container. It handles SIGTERM and SIGINT
by requesting shutdown, allowing the current processing attempt to finish and then stopping before the next poll.
Idle waiting responds to stop requests. Previous signal handlers and asynchronous dispatch settings are restored before
application shutdown. A forceful kill cannot perform this cleanup. Set your supervisor's shutdown grace period long
enough for in-flight delivery and acknowledgement. `--once` and command help do not require PCNTL.

Unhandled processing failures stop the worker, restore signal control, and shut down the application. With
`ExceptionHandlingCliRunner`, the default presenter writes a generic error to stderr and returns exit code 1; graceful
shutdown and a successful single poll return 0. Use a custom exception presenter for diagnostic logging. A process
supervisor can restart failed workers; the worker does not retry infrastructure failures in its loop.

The command owns polling and shutdown only. Transactions remain inside the persistence operations; external delivery
and the worker loop are not wrapped in `TransactionalCommandMiddleware`.

For another runtime, override `Integration\Outbox\Worker\WorkerControl` with your own stop and waiting implementation.
`Integration\Outbox\Exception\InvalidOutboxConfigurationException` reports invalid settings, and
`WorkerControlException` reports lifecycle failures. If processing and control cleanup both fail, `WorkerRunException`
preserves the processing exception as its previous exception and the cleanup error as `cleanupFailure`. These exceptions
implement `IntegrationException`.

## Claim committed messages

Use `Processing\OutboxStore` from a worker outside application transactions. Inject the existing
[`Clock`](../clock/README.md) into the store adapter so it determines the current time for each operation. The worker
supplies a lease duration:

```php
use DateInterval;

// $store implements OutboxStore and receives a Clock through its constructor.
$claim = $store->claim(new DateInterval('PT1M'));

if ($claim === null) {
    return; // No message is currently eligible.
}
```

Choose a lease duration that allows the delivery operation to finish. The store adds it to the current time used for
acquisition; it must produce a strictly later expiry. A claim owns one committed message exclusively until its expiry.
Newly committed messages are immediately eligible; retries become eligible at their scheduled time.
Expired claims can be reclaimed, including at the exact expiry instant. Selection order is not guaranteed.

The returned `Processing\ClaimedMessage` contains the original `message`, an `attempt` number, an opaque `token`, and
`expiresAt`. Attempts start at one and increase on each acquisition, including recovery after a worker stops before
performing delivery. Each acquisition uses a token never reused for that message. Claim construction validates a
positive attempt and non-empty token; constructing the value does not acquire ownership or check the current time.

## Record a processing outcome

After acquiring a claim, deliver its message outside persistence transactions, then record one outcome:

- `complete($claim)`: acknowledge successful delivery and exclude the message from future claims.
- `retry($claim, $availableAt)`: release ownership and schedule another attempt. A due time at or before now
  allows immediate retry.
- `fail($claim)`: record terminal failure and exclude the message from future claims.

The store determines the current time for each operation. Every outcome must atomically verify the message identifier
and ownership token against the stored active claim and require its stored expiry to be strictly after that time.
Missing, expired, superseded, and already released claims are rejected without changing state. Repeating an outcome
with the same claim therefore raises `LostClaimException`. An expired worker must stop updating that message.

Each store operation is durable on successful return. Delivery and completion cannot be atomic: delivery may succeed
before a worker stops or recording completion fails, allowing delivery again after expiry. Consumers should use the
stable message identifier for idempotency. A store failure after delivery is a persistence failure, not evidence that
delivery failed. Claims prevent simultaneous ownership, but an expired worker may still have an external request in
flight when another worker acquires the message.

## Handle failures

`OutboxException` is the component's root exception contract. Invalid envelope data raises
`Exception\InvalidOutboxMessageException` during construction. An adapter must throw `Exception\OutboxWriteException`
when no transaction is active, a message identifier already exists, or persistence fails. Duplicate writes must not
replace existing messages. Persistence failures must retain their lower-level cause as the previous exception.

When implementing an adapter, integration-test commit and rollback with application writes, rejection of writes
outside a transaction, duplicate identifiers, and visibility to a separate processing connection only after commit.

Processing exceptions live under `Processing\Exception` and implement `OutboxException`:

- `InvalidClaimException`: claim data is invalid, or a lease duration cannot be applied or does not produce a future
  expiry.
- `LostClaimException`: an outcome cannot be recorded because the supplied claim no longer owns the message.
- `OutboxStoreException`: persistence or obtaining the current time failed while claiming or recording an outcome.
  Preserve the underlying persistence exception or `ClockException` as the previous exception.

When implementing a store adapter, integration-test concurrent claims, visibility only after producer commit, due-time
and expiry boundaries, increasing attempts and fresh tokens on reclaim, stale-token rejection, and all three outcome
transitions. Verify that terminal outcomes remain ineligible, invalid lease durations acquire no claim, and persistence
and clock failures preserve their causes. Inject `FrozenClock` for deterministic time boundaries, using another frozen
clock instance when constructing an adapter for a later instant.
