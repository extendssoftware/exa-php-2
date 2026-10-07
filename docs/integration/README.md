# Integration

The `ExtendsSoftware\ExaPHP\Integration` namespace contains application modules and adapters that connect framework components.
`IntegrationException` is the root exception contract for integration failures.

## Register the CQRS module

Register `CqrsModule` before application bootstrap. The application configuration directory must already exist:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Integration\Cqrs\CqrsModule;

$application = new Application(__DIR__ . '/config');
$application->registerModule(CqrsModule::class);
$serviceLocator = $application->bootstrap();
```

`CqrsModule` loads its service configuration during bootstrap, registering `CommandBus` and `QueryBus` with their
synchronous factories. Resolve either interface through the service locator. Both are shared services and can be
resolved without handler mappings, producing empty buses.
See the [CQRS guide](../cqrs/README.md) for constructing and using the buses directly.

## Configure handlers

`CommandBusFactory::create()` and `QueryBusFactory::create()` receive a `ServiceLocator` and read its `Configuration`
service. Configure `cqrs.command.handlers` and `cqrs.query.handlers` as maps from message class names to handler service
identifiers.
The identifiers may be class names or application-defined strings.

With `CqrsModule` registered, a business module or application configuration file can contain the following.
The `App\Article` classes must be autoloadable;
this example assumes the handlers have no required constructor arguments. Use factory definitions for handlers with
injected dependencies.

```php
<?php

declare(strict_types=1);

use App\Article\CreateArticle;
use App\Article\CreateArticleHandler;
use App\Article\FindArticleTitle;
use App\Article\FindArticleTitleHandler;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;

return [
    'cqrs' => [
        'command' => [
            'handlers' => [CreateArticle::class => CreateArticleHandler::class],
        ],
        'query' => [
            'handlers' => [FindArticleTitle::class => FindArticleTitleHandler::class],
        ],
    ],
    'services' => [
        CreateArticleHandler::class => new InvokableDefinition(CreateArticleHandler::class),
        FindArticleTitleHandler::class => new InvokableDefinition(FindArticleTitleHandler::class),
    ],
];
```

Each factory creates a new synchronous bus and resolves all handlers in its map immediately. The service locator
controls
sharing of handler and bus services. Missing `cqrs` or the relevant handler map produces an empty bus.

A present `cqrs` section and each consumed map must be arrays. Map keys and service identifiers must be non-empty
strings.
Invalid configuration, including an incorrect configuration service type, raises
`Integration\Cqrs\Exception\InvalidCqrsConfigurationException`, which implements `IntegrationException`.
The buses validate message classes and resolved handler contracts using the
[CQRS registration rules](../cqrs/README.md#synchronous-registration-rules).

Direct factory calls propagate service-resolution and CQRS registration exceptions unchanged. When called through a
`FactoryDefinition`, the service locator preserves its own exceptions and wraps other failures in
`ServiceResolutionException`, retaining the original failure as the previous exception.

Override either bus definition in an application `/config/*.global.php` file when a different implementation is needed.
See [application configuration](../application/configuration.md) for merge and override rules.

## Register the Event module

Register `ExtendsSoftware\ExaPHP\Integration\Event\EventModule` before bootstrap to make
`ExtendsSoftware\ExaPHP\Event\EventDispatcher` available as a shared service. The module's service configuration uses
`EventDispatcherFactory::create()` to construct a `SynchronousEventDispatcher`. Without an `events` section, it creates
an empty dispatcher.

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Event\EventDispatcher;
use ExtendsSoftware\ExaPHP\Integration\Event\EventModule;

$application = new Application(__DIR__ . '/config');
$application->registerModule(EventModule::class);
$services = $application->bootstrap();
$dispatcher = $services->get(EventDispatcher::class);
```

The application configuration directory must exist. Business modules contribute keyed listener maps under `events`.
For example, assuming the application classes below are autoloadable and the listener needs no constructor arguments:

```php
<?php

declare(strict_types=1);

use App\Article\ArticleCreated;
use App\Search\UpdateArticleIndex;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;

return [
    'events' => [
        ArticleCreated::class => [
            UpdateArticleIndex::class => UpdateArticleIndex::class,
        ],
    ],
    'services' => [
        UpdateArticleIndex::class => new InvokableDefinition(UpdateArticleIndex::class),
    ],
];
```

Another module can add a different listener key for the same event. Associative maps merge, retaining both
registrations.
Use the listener's service FQCN as both key and value by convention; other non-empty string keys and service IDs are
also
accepted. Numeric listener lists are rejected. Each event may have an empty map.

Keys identify registrations; values identify services to resolve. Reusing a key replaces its value according to the
[configuration merge rules](../application/configuration.md). Distinct keys pointing to the same service cause repeated
invocations. Listener order follows merged map iteration order: new keys append, and replacement of an existing key
retains its position. Application configuration can replace a registration by key or clear an event's map with `[]`.

`EventDispatcherFactory` resolves listener services eagerly and converts each map into a listener list. The dispatcher
then applies its [registration validation and dispatch
rules](../event/README.md#register-listeners-for-synchronous-dispatch).
The factory does not invoke listeners while constructing the dispatcher.

Malformed configuration raises `Integration\Event\Exception\InvalidEventConfigurationException`, implementing
`IntegrationException`. Direct factory calls preserve service-resolution and Event exceptions unchanged. Resolution
through `FactoryDefinition` wraps non-ServiceLocator exceptions in `ServiceResolutionException`, preserving the cause.

Override the `EventDispatcher` service in application configuration to select a different implementation.

## Configure command middleware

Set `cqrs.command.middleware` to an ordered list of middleware service identifiers. Register those services under
`services` using the existing service definitions. For example, when `AuditCommand` and `AuthorizeCommand` are
registered:

```php
'cqrs' => [
    'command' => [
        'middleware' => [AuditCommand::class, AuthorizeCommand::class],
    ],
],
```

The command bus factory resolves these services eagerly and passes them to the bus in list order. The first entry wraps
all subsequent entries and the handler. A missing list means no middleware. Invalid configuration uses
`InvalidCqrsConfigurationException`; a resolved object that does not implement `CommandMiddleware` is rejected by the
bus
with `InvalidCommandMiddlewareException`. As with other factory failures, service resolution may wrap that exception.

Configure the pipeline at application level: numeric lists replace earlier lists during merging, rather than append.
See [command middleware](../cqrs/README.md#command-middleware) for execution and context semantics.

## Configure query middleware

Set `cqrs.query.middleware` to an ordered list of query middleware service identifiers. Register the referenced services
under `services`; they must implement `QueryMiddleware`. For example, with the application services below registered:

```php
'cqrs' => [
    'query' => [
        'handlers' => [FindArticleTitle::class => FindArticleTitleHandler::class],
        'middleware' => [AuthorizeQuery::class, CacheQuery::class],
    ],
],
```

The factory resolves handlers and middleware eagerly. Middleware runs in list order, with the first entry outermost.
A missing query section, handler map, or middleware list defaults to empty. Present sections must be arrays, and
middleware
must be a list of non-empty service identifiers. Malformed configuration raises `InvalidCqrsConfigurationException`;
invalid resolved middleware raises `InvalidQueryMiddlewareException` when the bus is constructed.

As with command middleware, configure the ordered pipeline at application level because lists replace earlier lists.
The handler map now lives at `cqrs.query.handlers`; move registrations previously stored under `cqrs.queries` there.
See [query middleware](../cqrs/README.md#query-middleware) for result and context semantics.

## Register the Clock module

Register `Integration\Clock\ClockModule` before bootstrap to provide a shared `Clock\Clock` service backed by
`Clock\SystemClock`. The application configuration directory must exist:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Clock\Clock;
use ExtendsSoftware\ExaPHP\Integration\Clock\ClockModule;

$application = new Application(__DIR__ . '/config');
$application->registerModule(ClockModule::class);
$services = $application->bootstrap();
$clock = $services->get(Clock::class);
```

Override `Clock::class` in application service configuration to supply another clock, such as a `FrozenClock` for tests:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Clock\Clock;
use ExtendsSoftware\ExaPHP\Clock\FrozenClock;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;

return ['services' => [
    Clock::class => new FactoryDefinition(
        static fn(): Clock => new FrozenClock(new DateTimeImmutable('2026-10-06T12:00:00Z')),
    ),
]];
```

## Register the Logging module

Register `Integration\Logging\LoggingModule` and [ClockModule](#register-the-clock-module) before bootstrap, or supply
your own `Clock\Clock` service. The application configuration directory must exist:

```php
use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Integration\Clock\ClockModule;
use ExtendsSoftware\ExaPHP\Integration\Logging\LoggingModule;
use ExtendsSoftware\ExaPHP\Logging\Logger;

$application = new Application(__DIR__ . '/config');
$application->registerModule(ClockModule::class);
$application->registerModule(LoggingModule::class);
$services = $application->bootstrap();
$logger = $services->get(Logger::class);
```

The logging module registers shared `Logger` and `Logging\Writer\LogWriter` services. `LoggerFactory` resolves
the writer and the separately registered clock to create a `WriterLogger`.
The default writer is a `StreamLogWriter` targeting `php://stderr` with NDJSON formatting.
Resolving these services does not write a record or open the destination.

Override `LogWriter::class` in an application file such as `/config/logging.global.php` to configure the destination:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Logging\Writer\LogWriter;
use ExtendsSoftware\ExaPHP\Logging\Writer\StreamLogWriter;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;

return [
    'services' => [
        LogWriter::class => new FactoryDefinition(
            static fn(): LogWriter => new StreamLogWriter(__DIR__ . '/../var/application.ndjson'),
        ),
    ],
];
```

Create the `var` directory before logging. Writer factories can construct `FilteringLogWriter` and `CompositeLogWriter`
combinations or resolve other registered writers. See the [logging guide](../logging/README.md) for composition and
failure behavior. No separate logging configuration schema is required. Override `Logger::class` itself to use a
different logger implementation.

A writer service that does not implement `LogWriter`, or a clock service that does not implement `Clock`, causes
`Integration\Logging\Exception\InvalidLoggingConfigurationException`, which implements `IntegrationException`.
Direct `LoggerFactory::create()` calls preserve service-resolution exceptions unchanged. Resolution through
`FactoryDefinition` wraps non-ServiceLocator exceptions in `ServiceResolutionException`, preserving the cause.

## Register the Messaging module

Register `Integration\Messaging\MessagingModule` with `CliModule` and `ClockModule`, or provide a shared `Clock`
service. The module configures the subscription registry, lazy subscriber resolver, processor, and `messaging:consume`
command. Applications supply a consumer adapter and a consumption retry policy. Consumption runs independently of
publishing and Outbox services. See the [Messaging CLI guide](../messaging/README.md#run-the-cli-consumer) for service
registration, subscription configuration, polling, and shutdown behavior.

## Publish Outbox messages through Messaging

`Integration\Messaging\Outbox\PublishingMessageDelivery` implements Outbox's `MessageDelivery` using an injected
`Messaging\MessagePublisher`. Register your application publisher and use `ReflectionDefinition` to bind
`MessageDelivery` to this bridge. See the [Messaging guide](../messaging/README.md#publish-through-outbox) for configuration,
acknowledgement guarantees, and failure handling.

## Resolve Messaging subscribers

`Integration\Messaging\Resolver\ServiceLocatorSubscriberResolver` implements
`Messaging\Subscription\SubscriberResolver` with explicit subscriber-to-service mappings. It resolves only the mapped
service on demand and checks that it implements `MessageSubscriber`, without invoking it. See
[subscriber resolution](../messaging/README.md#resolve-subscribers) for configuration and failure behavior.

## Register the Outbox module

Register `Integration\Outbox\OutboxModule` with `CliModule` and `ClockModule`, or provide a shared `Clock` service.
The module registers `OutboxProcessor`, worker settings, and the `outbox:work` command. It uses worker control from
`CliModule`. Applications provide `OutboxStore`, `MessageDelivery`, and `RetryPolicy` services. Command help is available
without resolving them.

Configure `outbox.worker.lease_seconds` (default 30) and `outbox.worker.idle_delay_seconds` (default 1) as positive integer
seconds. The persistent worker initializes the application once, polls until shutdown, and requires PCNTL for the default
signal control. `--once` performs one poll without requiring PCNTL. See the
[Outbox worker guide](../outbox/README.md#run-the-cli-worker) for service configuration, the CLI entry point, shutdown
behavior, and failure reporting.

## Register the HTTP module

Register `HttpModule` before modules that contribute HTTP middleware, so its default exception boundary is outermost.
The application configuration directory must exist:

```php
use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Integration\Http\HttpModule;

$application = new Application(__DIR__ . '/config');
$application->registerModule(HttpModule::class);
// Register your business modules here.
$serviceLocator = $application->bootstrap();
```

The module supplies these shared services:

| Service identifier | Default implementation |
| --- | --- |
| `Http\Handler\RequestHandler` | `MiddlewarePipeline` wrapping the routing handler |
| `Http\Routing\Router` | `SimpleRouter` using `http.routes` |
| `Http\Handler\HandlerResolver` | `ServiceLocatorHandlerResolver` |
| `Http\Routing\RoutingRequestHandler` | Routing and lazy handler dispatch |
| `Http\Middleware\ExceptionHandlingMiddleware` | Configured exception response factory |
| `Http\ErrorHandling\ProblemDetails\ProblemDetailsResponseFactory` | JSON Problem Details response creation |
| `Http\ErrorHandling\ExceptionResponseFactory` | Ordered exception mappers with decoding and generic 500 fallback |
| `Http\Server\ServerRequestFactory` | `PhpServerRequestFactory` |
| `Http\Server\ResponseEmitter` | `PhpResponseEmitter` |

Identifiers above are relative to `ExtendsSoftware\ExaPHP`. Override service definitions in application configuration
for custom routers, exception responses, or server adapters. Resolving the pipeline validates configuration and resolves
middleware eagerly; handler services are resolved only after a route matches. An empty router returns 404.

### Configure routes and middleware

Each module can contribute a configuration file under its own configuration directory. Application overrides belong
in `/config/*.global.php` or `*.local.php`. For an autoloadable, constructorless application `ArticleHandler` implementing
`RequestHandler`, a configuration file can contain:

```php
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Routing\Route;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;

return [
    'http' => [
        'routes' => [
            'articles.show' => new Route('articles.show', Method::Get, '/articles/{id}', ArticleHandler::class),
        ],
    ],
    'services' => [
        ArticleHandler::class => new InvokableDefinition(ArticleHandler::class),
    ],
];
```

Use `FactoryDefinition` for handlers with dependencies. Configuration keys identify entries for merging; each
`Route::$name` is the authoritative identifier for URL generation and is available through `RouteMatch::$route`.
Use matching keys and route names for readability. The router receives the values in registration order and applies
its normal [matching rules](../http/README.md#path-patterns-and-precedence). Duplicate method/path structures still fail,
even when their configuration names differ. Route names must also be unique across all methods and paths.

`http.middleware` is an ordered map from non-empty registration names to non-empty service identifiers. The module
provides `['exceptions' => ExceptionHandlingMiddleware::class]`. Business modules can append entries such as
`['audit' => AuditMiddleware::class]` and register those services. Middleware must implement the HTTP `Middleware` contract.
The first registration runs outermost. Reusing a service under multiple names runs it multiple times.

Associative configuration merges by name. Replacing an existing route or middleware entry retains its position;
new names append in source order. An explicit empty array replaces the section and clears registrations, including the
default exception middleware when applied to `http.middleware`. To rebuild an order entirely, clear the section in an
earlier application configuration file and supply the replacement map in a later file.

Do not use a numeric list for routes or middleware. Factories reject malformed sections or entries with
`InvalidHttpConfigurationException`, which implements `IntegrationException`. They propagate HTTP registration errors
and service resolution failures unchanged. When invoked through the service locator, non-locator factory failures
are wrapped in `ServiceResolutionException`, with the original cause available through `getPrevious()`. Failures
constructing the pipeline occur before its exception middleware can run.

### Configure request body decoding

`HttpModule` registers `Http\Decoding\RequestBodyDecoder` as a `ContentTypeRequestBodyDecoder`, and registers
`JsonRequestBodyDecoder` with a one-MiB default input limit. Inject the decoder into handlers and call `decode($request)`
only when their request body is needed. A configuration override can set:

```php
use ExtendsSoftware\ExaPHP\Http\Decoding\JsonRequestBodyDecoder;

return [
    'http' => [
        'request' => [
            'decoders' => ['application/json' => JsonRequestBodyDecoder::class],
            'json' => ['maxBytes' => 2097152],
        ],
    ],
];
```

`maxBytes` must be a non-negative integer and applies to the JSON decoder. Other decoders own their limits and decoding
policies. Add exact media-type-to-service mappings to `http.request.decoders` to support additional formats; each service
must implement `RequestBodyDecoder`. Parameters are not permitted in registration keys. Services are resolved when the
dispatcher is constructed; bodies are read only when `decode()` is called. An empty map supports no input formats.

The default exception response service tries configured mappers first, then maps decoding failures to 400, 413,
and 415, and delegates all others to the Problem Details 500 factory. Overriding that service replaces this policy. See the
[request decoding guide](../http/README.md#decode-json-request-bodies) for body consumption and format limitations.

### Configure response representations

`HttpModule` registers `Http\Representation\ContentNegotiatingResponseFactory` and
`Http\Representation\JsonResponseFactory`. Inject the negotiator into handlers that return representation data.
The default configuration is:

```php
use ExtendsSoftware\ExaPHP\Http\Representation\JsonResponseFactory;

return [
    'http' => [
        'response' => [
            'default' => 'application/json',
            'factories' => ['application/json' => JsonResponseFactory::class],
        ],
    ],
];
```

Modules can add media-type-to-service mappings under `http.response.factories`. For example, after implementing an
application `XmlResponseFactory` that implements the HTTP `ResponseFactory` contract, add:

```php
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;

return [
    'http' => ['response' => ['factories' => ['application/xml' => XmlResponseFactory::class]]],
    'services' => [XmlResponseFactory::class => new InvokableDefinition(XmlResponseFactory::class)],
];
```

This adds XML while retaining JSON as the default. Use application configuration to set `http.response.default` to
another registered media type. Media types must be concrete and parameterless, and case-insensitively unique. Factory
services are resolved when the negotiator is constructed; encoding occurs only for the selected factory per request.
An empty factory map or a default without a registered factory is invalid. No XML encoder is supplied by the framework.
See [negotiation behavior](../http/README.md#negotiate-response-representations) for quality weights and 406 responses.

### Run the application through HTTP

Keep module selection in application-owned setup, for example `config/application.php`. This filename is not a
`*.global.php` or `*.local.php` configuration source, so it is not loaded by configuration discovery:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Integration\Http\HttpModule;

$application = new Application(__DIR__);
$application->registerModule(HttpModule::class);
// Register your business and other integration modules here.

return $application;
```

Then `public/index.php` can delegate execution to `Integration\Http\HttpRunner`:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Integration\Http\HttpRunner;

require dirname(__DIR__) . '/vendor/autoload.php';
$application = require dirname(__DIR__) . '/config/application.php';

new HttpRunner()->run($application);
```

The runner bootstraps the application, resolves and validates `ServerRequestFactory`, `RequestHandler`, and
`ResponseEmitter`, creates one request, passes it through the pipeline, and emits the response with the original request
method. It then shuts down the application. It never registers modules automatically, emits fallback responses, or
terminates the process. A stopped application cannot be run again; construct a new application for another lifecycle.

After successful bootstrap, shutdown is attempted even if service resolution, request creation, handling, or emission
fails. Bootstrap failures retain Application's own cleanup behavior. A single execution or shutdown failure propagates
unchanged. If both fail, `HttpRunException` preserves the execution failure as `getPrevious()` and the shutdown failure
as `shutdownFailure`. The latter can contain aggregated module cleanup failures.

The default exception middleware covers downstream handling, including request decoding invoked by handlers. Failures outside that boundary still reach the caller of
`run()`, and an emitted response cannot be retracted. See the
[HTTP server guide](../http/README.md#serve-a-request-through-php) for stream ownership and emission limitations.
Applications needing a different lifecycle can still invoke the request factory, handler, and emitter directly.

### Resolve HTTP handlers

For manual wiring without `HttpModule`, construct `Http\Resolver\ServiceLocatorHandlerResolver` from this Integration
namespace with the application's service locator and pass it to `RoutingRequestHandler` alongside a router.
The adapter checks that resolved services implement `RequestHandler`. A wrong type or service locator failure produces
HTTP `HandlerResolutionException`, preserving the locator failure as the previous exception. Service definitions control
handler sharing and construction; neither matching nor 404/405 responses resolve handlers.

## Register and run CLI commands

`CliModule` also registers shared `Cli\Worker\WorkerControl` using `PcntlWorkerControl` for cooperative shutdown and idle
waiting in long-running commands. See [CLI worker control](../cli/README.md#control-long-running-workers) for lifecycle
and runtime requirements.

Register `Integration\Cli\CliModule` before bootstrap. It provides shared `CommandRegistry`, `InputParser`,
`HandlerResolver`, `CommandDispatcher`, `HelpRenderer`, and `Output` services. The default registry is empty, parsing uses
`ArgvInputParser`, and `StreamOutput` borrows PHP's `STDOUT` and `STDERR`. Override the `Output` service for other
streams or environments where those runtime constants are unavailable.

Modules register named definitions under `cli.commands` and supply their handler services:

```php
<?php

declare(strict_types=1);

use App\GreetingHandler;
use ExtendsSoftware\ExaPHP\Cli\Definition\ArgumentDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\CommandDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;

return [
    'cli' => [
        'commands' => [
            'greet' => new CommandDefinition('greet', GreetingHandler::class, arguments: [
                new ArgumentDefinition('name'),
            ]),
        ],
    ],
    'services' => [
        GreetingHandler::class => new InvokableDefinition(GreetingHandler::class),
    ],
];
```

`App\GreetingHandler` must implement the [CLI handler contract](../cli/README.md#handle-parsed-input).
Configuration keys identify registrations for merging and application overrides; the definition's `name` is the actual
command name. Distinct registration keys with duplicate command names are rejected. Handlers are resolved only after
command lookup and parsing succeed. The locator adapter translates locator failures and incompatible handler services
into `Cli\Handler\Exception\HandlerResolutionException`, preserving locator failures as the previous exception.

Create `config/application.php` to register `CliModule` and your business modules and return an unbootstrapped
`Application`, as in the HTTP setup above. A shared application configuration can register both HTTP and CLI modules.
Then `bin/console.php` runs one command:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Integration\Cli\CliRunner;

require dirname(__DIR__) . '/vendor/autoload.php';
$application = require dirname(__DIR__) . '/config/application.php';

exit(new CliRunner()->run($application, $argv));
```

Invoke it as `php bin/console.php greet World`. Pass the complete argument list, including the script path and command
name when executing a command. `CliRunner` validates this structure before bootstrap, dispatches the selected command,
attempts application shutdown, and returns the handler's integer exit code. The entry point owns process termination. The runner neither
registers modules nor prints failures or converts them into exit codes; handle exceptions in your entry point if needed.
Malformed argument lists or missing script paths raise `Integration\Cli\Exception\InvalidCliArgumentsException`.
With only a script path, the runner displays the command listing.

After successful bootstrap, shutdown is attempted even after lookup, parsing, resolution, output, or handler failures.
Bootstrap failure cleanup belongs to `Application`. A single execution or shutdown failure propagates unchanged. If both
fail, `CliRunException` preserves execution failure as `getPrevious()` and shutdown failure as `shutdownFailure`.
A stopped application cannot run again; create a new application for another lifecycle.

Malformed CLI configuration or incompatible integration collaborators raise `InvalidCliConfigurationException` from
factories, implementing `IntegrationException`. Factories invoked by the service locator follow its exception contract:
factory failures are wrapped in `ServiceResolutionException` with the original failure retained. Direct runner service
type checks raise `InvalidCliConfigurationException` without wrapping. See the [CLI guide](../cli/README.md) for command
syntax, parsing exceptions, and stream behavior.

### Display CLI help

The runner supports these invocations without resolving a dispatcher, parser, or command handler:

```sh
php bin/console.php
php bin/console.php --list
php bin/console.php --help
php bin/console.php -h
php bin/console.php greet --help
php bin/console.php greet -h
```

Global help lists registered commands in configuration order with their descriptions. Command help shows usage,
required and optional positional arguments, flags, aliases, and required option values. Both write plain text to stdout,
return `ExitCode::Success->value`, and run application shutdown. Help requires only the `CommandRegistry`, `HelpRenderer`,
and `Output` services. Unknown commands requested through help raise `CommandNotFoundException`.

Command help is recognized only when `--help` or `-h` is the sole token after the command name. These two forms are
reserved by the runner, including for commands defining their own help option. Other token combinations go through
ordinary parsing. Tokens after `--` retain their normal positional meaning. Global `--list`, `--help`, and `-h` are
recognized only when supplied alone; command names such as `list` and `help` remain available to applications.

### Present CLI failures

Use `ExceptionHandlingCliRunner` at the entry point when failures should become terminal messages and exit codes:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Cli\Output\StreamOutput;
use ExtendsSoftware\ExaPHP\Integration\Cli\ExceptionHandlingCliRunner;

require dirname(__DIR__) . '/vendor/autoload.php';
$application = require dirname(__DIR__) . '/config/application.php';
$errorOutput = new StreamOutput(STDOUT, STDERR);

exit(new ExceptionHandlingCliRunner()->run($application, $argv, $errorOutput));
```

Supply error output independently of application services so bootstrap failures can be presented. Normal command
output still uses the application's `Output` service. Successful command exit codes are unchanged. The wrapper handles
failures from its `CliRunner::run()` call; errors loading autoloading or application configuration in the entry point are
outside this boundary.

The default presenter writes one line to stderr. Unknown commands, parser errors, and malformed process arguments
implement `Cli\ErrorHandling\UsageException`: their messages are shown with exit code `InvalidUsage` (2). Carriage
returns, newlines, and escape bytes in those messages are replaced with spaces. Other failures return `Failure` (1)
with `Error: Command execution failed.`; exception details and traces are hidden. A combined execution and shutdown
failure also returns 1 rather than a usage code, even when its execution cause was invalid usage.

Presentation runs after the underlying runner's cleanup attempts. Output or presenter failures propagate unchanged,
without recursive presentation. Neither the wrapper nor the presenter calls `exit()`. Direct `CliRunner` usage continues
to propagate failures without printing them.

For application-specific messages or logging, implement `Cli\ErrorHandling\ExceptionPresenter` and pass it through
`new ExceptionHandlingCliRunner(presenter: $presenter)`. The presenter receives the original exception and the error
output and returns a nonzero integer exit code. For `CliRunException`, both execution and shutdown failures remain
available. The default presenter performs no logging and adds no dependency on the Logging component.

## Wrap commands in transactions

`Integration\Transaction\Middleware\TransactionalCommandMiddleware` accepts an application-provided
`TransactionManager` adapter and wraps the remaining command pipeline. Register it as a service and include its
identifier in `cqrs.command.middleware` when transactional commands are required. The middleware is not registered
automatically. See the [Transaction guide](../transaction/README.md#wrap-cqrs-commands) for ordering, nesting, and
failure behavior.

### Customize HTTP problem responses

`HttpModule` registers `Http\ErrorHandling\ProblemDetails\ProblemDetailsResponseFactory` for application factories to inject.
Framework routing, negotiation, decoding, and fallback exception errors produce Problem Details by default.
To map application exceptions, register your implementation under `ExceptionResponseFactory::class`; wrap it with
`RequestBodyExceptionResponseFactory` when retaining the framework's decoding policy. See the
[Problem Details guide](../http/README.md#create-problem-details-responses) and the application exception factory example
in [HTTP error handling](../http/README.md).

### Register module exception mappers

`HttpModule` creates a `MappingExceptionResponseFactory` for the `ExceptionResponseFactory` service. Modules contribute
named service identifiers under `http.exceptionMappers`:

```php
use App\Article\Http\ArticleProblemDetailsMapper;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;

return [
    'http' => [
        'exceptionMappers' => [
            'article' => ArticleProblemDetailsMapper::class,
        ],
    ],
    'services' => [
        ArticleProblemDetailsMapper::class => new InvokableDefinition(ArticleProblemDetailsMapper::class),
    ],
];
```

Each service must implement `Http\ErrorHandling\ProblemDetails\ExceptionProblemDetailsMapper`. Registration names and service identifiers
must be nonempty strings. Missing `http` or `exceptionMappers` sections mean no mappers; explicitly invalid section
values are rejected. Services are resolved when the exception response factory is created, and run in configuration
order. Module maps merge by name; an application override replaces the named mapper while retaining its position.
The first mapper returning a problem wins. Register specific mappings before broad ones.

Configured mappers run before framework defaults. With no match, request decoding errors retain their 400/413/415
responses and unexpected errors retain the generic 500. Routing 404/405 and negotiation 406 are normal responses and do
not invoke exception mappers. Mapper failures propagate through the existing exception factory failure behavior.

The composing factory uses the registered `ProblemDetailsResponseFactory` service. Overriding
`ExceptionResponseFactory::class` entirely replaces this composition and its defaults. Configuration errors raise
`InvalidHttpConfigurationException`; incompatible mapper instances raise `InvalidExceptionProblemDetailsMapperException`.
As with other service factories, failures during locator resolution are retained inside `ServiceResolutionException`.

### Generate URLs from configured routes

`HttpModule` registers a shared `Routing\RouteCollection` from `http.routes`. Both `Router` and `UrlGenerator` use that
collection, so application overrides affect matching and generation together. Definitions are validated when the
collection is resolved; handler services remain lazy.

```php
use ExtendsSoftware\ExaPHP\Http\Routing\UrlGenerator;

$urls = $services->get(UrlGenerator::class);
$uri = $urls->generate('articles.show', ['id' => 123], ['include' => 'author']);
// /articles/123?include=author
```

Inject `UrlGenerator` into application handlers needing links. Route names are case-sensitive, nonempty, and globally
unique within the collection. A configuration key may differ from the route name; generation uses the definition's
name. See [URL generation](../http/README.md#generate-route-urls) for encoding rules and failure behavior.
