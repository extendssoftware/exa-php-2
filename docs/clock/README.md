# Clock

Inject `ExtendsSoftware\ExaPHP\Clock\Clock` into application services that need the current time. Its `now()` method
returns a `DateTimeImmutable` so callers can calculate other times without changing the clock's returned value.

## Read the system time

Use `SystemClock` for the current system time. It returns UTC regardless of PHP's default timezone:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Clock\SystemClock;

$clock = new SystemClock();
$createdAt = $clock->now();
```

System time can change when the operating system adjusts its clock. Use this clock for timestamps, rather than
measuring elapsed durations.

## Fix time in tests

Pass a `FrozenClock` to the same service to make time-dependent behavior reproducible:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Clock\FrozenClock;

$time = new DateTimeImmutable('2026-10-06T12:34:56.123456+02:00');
$clock = new FrozenClock($time);

assert($clock->now() === $time);
```

Every call returns the supplied immutable value, preserving its timezone and microseconds. Create another frozen
clock to represent a different time. The `Clock` contract does not require UTC; consumers that need a particular
timezone should normalize the returned value explicitly.

For an outbox producer, pass `$clock->now()` as the `createdAt` argument when constructing an
[`OutboxMessage`](../outbox/README.md). Choose the clock when composing the application and inject it into the producer.

## Register a shared clock

Register [ClockModule](../integration/README.md#register-the-clock-module) to make a shared `SystemClock` available as
`Clock::class` through the service locator. Application service configuration can override it with another clock.
The logging integration requires this registration or an application-provided `Clock` service.

## Provide another time source

Implement `Clock::now()` to return the current time for your source. Report failures through an exception implementing
`ClockException`. The logging component translates these failures into its own exception contract; see
[logging timestamps](../logging/README.md) for clock injection and failure behavior.
