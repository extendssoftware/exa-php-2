# Logging

The `ExtendsSoftware\ExaPHP\Logging` namespace provides logging to streams, logger and formatter contracts, log records,
severity levels, and a root exception contract. It has no PSR or third-party dependencies.

## Submit a diagnostic message

Inject `Logger` into services that need logging. Create a logger with a stream writer and inject it through that interface:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Logging\LogLevel;
use ExtendsSoftware\ExaPHP\Logging\Writer\StreamLogWriter;
use ExtendsSoftware\ExaPHP\Logging\WriterLogger;

$logger = new WriterLogger(new StreamLogWriter(__DIR__ . '/application.log'));
$logger->log(LogLevel::Info, 'Article published.', [
    'articleId' => 'article-1',
    'actorId' => 'user-42',
]);
```

`log()` accepts a string message and optional context, and returns nothing. Context is a map with descriptive string
keys and arbitrary values. The contract does not define placeholder interpolation, serialization, or reserved context
keys; callers should not assume those behaviors.

To register logging services with an application, use the
[Logging integration module](../integration/README.md#register-the-logging-module).

## Write to a stream

`WriterLogger` creates one `LogRecord` per call with the current UTC timestamp and passes it to its `LogWriter`.
Writers receive existing records, allowing multiple destinations to use the same timestamp and context.

`Logging\Writer\StreamLogWriter` accepts a file path, a stream URI such as `php://stdout` or `php://stderr`, or an
existing writable stream resource. It uses `JsonLogFormatter` by default. Pass another `LogFormatter` as the second
constructor argument to change the output. The writer adds no delimiters of its own.

```php
$logger = new WriterLogger(new StreamLogWriter('php://stderr'));
```

Path destinations are opened in binary append mode and closed for each record. Parent directories must already exist.
Supplied resources remain open and are owned by the caller; seekable streams are positioned at their end before writing.
Formatting completes before the destination is opened or changed.

Regular local files use an exclusive advisory lock during writing and flushing. Other writers must cooperate with
locking for this protection to apply. Other streams, including stdout and stderr, have no interprocess atomicity
guarantee. Flushing does not guarantee durable storage. Positive partial writes are completed, while zero progress
fails. A failure can leave part of a record written; delivery is not retried automatically.

Stream failures implement `LoggingException`:

- `InvalidLogStreamException`: invalid destination, read-only resource, or a supplied resource closed before writing.
- `LogStreamOpenException`: a path or URI could not be opened.
- `LogStreamWriteException`: writing, seeking, locking, flushing, or cleanup failed.

These exceptions live in `Logging\Exception`. Converted stream warnings retain their cause as the previous exception.
Formatter exceptions propagate unchanged. To add a destination, implement `Logging\Writer\LogWriter::write()`;
accept the existing record without modifying it or its context objects.

## Choose a severity

`LogLevel` is a string-backed enum:

| Level | Intended meaning |
| --- | --- |
| `Debug` | Detailed diagnostic information |
| `Info` | Normal operation |
| `Notice` | Normal but significant occurrence |
| `Warning` | Unexpected condition needing attention |
| `Error` | Failed operation |
| `Critical` | Critical condition affecting operation |
| `Alert` | Immediate action required |
| `Emergency` | System unusable |

Backing values are lowercase, such as `LogLevel::Warning->value === 'warning'`. They are names, not numeric priorities.

## Filter records for a destination

Wrap a writer in `Logging\Writer\FilteringLogWriter` and supply a `callable(LogRecord): bool` predicate. It evaluates
each record once, forwards the same record when accepted, and silently discards rejected records without calling the
wrapped writer. Predicates can inspect severity, message, and context, but must not modify context objects.

For example, write critical and more severe records to a file:

```php
use ExtendsSoftware\ExaPHP\Logging\LogLevel;
use ExtendsSoftware\ExaPHP\Logging\LogRecord;
use ExtendsSoftware\ExaPHP\Logging\Writer\FilteringLogWriter;
use ExtendsSoftware\ExaPHP\Logging\Writer\StreamLogWriter;
use ExtendsSoftware\ExaPHP\Logging\WriterLogger;

$writer = new FilteringLogWriter(
    new StreamLogWriter(__DIR__ . '/critical.log'),
    static fn(LogRecord $record): bool => match ($record->level) {
        LogLevel::Critical, LogLevel::Alert, LogLevel::Emergency => true,
        default => false,
    },
);
$logger = new WriterLogger($writer);
```

For an exact level, use a predicate such as `static fn(LogRecord $record): bool => $record->level === LogLevel::Debug`.
Select severity cases explicitly; the enum's string values do not define severity ordering.
Predicate and wrapped-writer exceptions propagate unchanged. If the predicate fails, the wrapped writer is not called.

## Send records to multiple destinations

`Logging\Writer\CompositeLogWriter` accepts writers as variadic constructor arguments and sends the same record to
each in registration order. Wrap individual destinations in `FilteringLogWriter` to give each its own filter:

```php
use ExtendsSoftware\ExaPHP\Logging\LogLevel;
use ExtendsSoftware\ExaPHP\Logging\LogRecord;
use ExtendsSoftware\ExaPHP\Logging\Writer\CompositeLogWriter;
use ExtendsSoftware\ExaPHP\Logging\Writer\FilteringLogWriter;
use ExtendsSoftware\ExaPHP\Logging\Writer\StreamLogWriter;
use ExtendsSoftware\ExaPHP\Logging\WriterLogger;

$logger = new WriterLogger(new CompositeLogWriter(
    new StreamLogWriter('php://stdout'),
    new FilteringLogWriter(
        new StreamLogWriter(__DIR__ . '/critical.log'),
        static fn(LogRecord $record): bool => $record->level === LogLevel::Critical,
    ),
));
```

Here every record goes to stdout, and records at exactly `Critical` also go to the file. Each destination receives the
original timestamp and context. An empty composite discards records. Registering the same writer more than once invokes
it for each occurrence.

When a writer throws `LoggingException`, remaining writers are still attempted. Afterwards, any collected failures
are reported in `Logging\Exception\CompositeLogWriteException`, even if only one writer failed. Its readonly
`failures` array contains the original exceptions keyed by zero-based writer position, in delivery order. The first
failure is also the previous exception. Nested composites retain their own aggregate exception rather than flattening it.

Exceptions outside `LoggingException`, including programming errors, stop delivery immediately and propagate unchanged.
Successful writes are not rolled back. The composite does not retry or log failures itself; retrying the whole operation
can duplicate records at destinations that already succeeded.

## Create a log record

`LogRecord` is a readonly value carrier with public `level`, `message`, `timestamp`, and `context` properties. Supply a
`DateTimeImmutable` explicitly so a record's time is determined once and can be reused across destinations:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Logging\LogLevel;
use ExtendsSoftware\ExaPHP\Logging\LogRecord;

$record = new LogRecord(
    level: LogLevel::Info,
    message: 'Article published.',
    timestamp: new DateTimeImmutable('2026-10-02T12:34:56.123456+00:00'),
    context: ['articleId' => 'article-1'],
);
```

The record preserves the supplied timestamp, including timezone and precision. Context defaults to an empty array.
Messages and context are stored without interpolation or normalization. Objects inside context are not cloned or frozen;
prefer immutable values when a stable record is required.

## Format a record

Implement `Logging\Formatter\LogFormatter::format(LogRecord $record): string` to convert a record into text. Formatting
must not write to a destination or modify the record or its context objects. Given a formatter instance:

```php
$formatted = $formatter->format($record);
```

Concrete formatters define the output representation, context serialization, and whether they include line terminators.
Formatting failures use `LoggingException`. The `Logger` interface still accepts a level, message, and context;
`LogRecord` is the data passed to a formatter, rather than a replacement for that public method signature.

## Format as NDJSON

`Logging\Formatter\JsonLogFormatter` produces compact UTF-8 JSON followed by one LF (`\n`). Each record occupies one
physical line; newlines within messages and exception details are escaped. It does not write to a stream.

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Logging\Formatter\JsonLogFormatter;
use ExtendsSoftware\ExaPHP\Logging\LogLevel;
use ExtendsSoftware\ExaPHP\Logging\LogRecord;

$record = new LogRecord(
    LogLevel::Info,
    'Article published.',
    new DateTimeImmutable('2026-10-02T12:34:56.123456+00:00'),
    ['articleId' => 'article-1'],
);

$line = new JsonLogFormatter()->format($record);
```

The result is a line such as:

```json
{"timestamp":"2026-10-02T12:34:56.123456Z","level":"info","message":"Article published.","context":{"articleId":"article-1"}}
```

The top-level fields are `timestamp`, `level`, `message`, and `context`. Timestamps are normalized to UTC
with microseconds and a `Z` suffix;
context is always a JSON object, including when empty. Message placeholders are not interpolated.

Context normalization follows these rules, including nested values:

- Scalars and null retain their values; arrays preserve keys and JSON's usual list/object distinction.
- `DateTimeInterface` values use the same UTC timestamp format. Original date objects are not modified.
- Backed enums use their backing value; other enums use their case name.
- Exceptions and engine errors include `class`, `message`, `code`, `file`, `line`, `trace`, `traceTruncated`, and
  `previous`.
  Traces contain at most 50 frames and only file, line, class, call type, and function fields. Arguments, referenced
objects,
  and custom exception properties are excluded. Previous exceptions use the same representation.
- Other objects become `{"class":"FullyQualifiedClassName"}`. Public properties, `JsonSerializable`, and `__toString()`
  are
  not evaluated. Extract desired fields into arrays explicitly.
- Open and closed resources are rejected.

Trace argument exclusion does not redact secrets already present in messages or explicit context; callers must choose
what to log. Normalization does not modify context objects.

`Logging\Exception\LogFormattingException` implements `LoggingException`. It is thrown for excessive context nesting
(over 32 levels, including previous-exception traversal), resources, invalid UTF-8, non-finite numbers, and output
exceeding
1 MiB including the newline. Cyclic arrays fail through the nesting limit. JSON encoding errors retain their
`JsonException` as the previous exception. Oversized records are rejected rather than emitted as invalid or partial
JSON;
the size check occurs after encoding and is not a bound on temporary memory use.

## Implement the contract

Implement `Logger::log(LogLevel $level, string $message, array $context = []): void`. Keep logging dependencies behind
this contract so application services do not depend on a particular destination. Document formatting, filtering,
destination behavior, and delivery guarantees on the implementation that provides them.

`LoggingException` extends `Throwable`. Logging-specific exceptions must implement it, allowing callers to handle
logging failures together. Implementations should preserve the original cause when translating a lower-level failure.
Callers decide whether a logging failure should interrupt their operation or be handled separately.

Operational logging alone does not establish an audit trail's persistence or delivery guarantees.
