# CLI

The `ExtendsSoftware\ExaPHP\Cli` component provides parsed input values and execution contracts for terminal commands.
CLI handlers translate terminal input into application operations and present their results.

## Handle parsed input

Implement `Handler\CommandHandler` to accept `Input\Input` and `Output\Output`, then return an integer exit code:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Cli\ExitCode;
use ExtendsSoftware\ExaPHP\Cli\Handler\CommandHandler;
use ExtendsSoftware\ExaPHP\Cli\Input\Input;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;

final readonly class GreetingHandler implements CommandHandler
{
    public function handle(Input $input, Output $output): int
    {
        $name = $input->arguments['name'] ?? '';
        if ($name === '') {
            $output->writeError("A name is required.\n");

            return ExitCode::InvalidUsage->value;
        }
        $output->write('Hello, ' . $name . "!\n");

        return ExitCode::Success->value;
    }
}

$input = new Input('greet', ['name' => 'Ada'], ['quiet' => false]);
// $output is supplied by your environment and implements Output.
$exitCode = new GreetingHandler()->handle($input, $output);
```

`Input` exposes readonly `command`, `arguments`, and `options` properties. Argument maps contain string values; option
maps contain strings for valued options and booleans for flags. Names are non-empty strings and remain case-sensitive.
Values are not trimmed or converted: `'0042'` remains a string and an empty string is preserved. Missing entries are
omitted. Use `array_key_exists()` to distinguish a present false flag from an absent option.

The value validates these structural constraints, not command definitions or application rules. Invalid input raises
`Input\Exception\InvalidInputException`, which implements `CliException`. Input construction does not read `$argv`.

## Write output and return status

`Output::write()` targets standard output; `writeError()` targets standard error. Both write the supplied bytes without
adding line endings. Implementations must report write failures through `CliException`; partial output may already
have been written. Application handlers should receive this boundary explicitly rather than printing directly.

Use `StreamOutput` with writable stream resources. In a CLI entry point, PHP provides `STDOUT` and `STDERR`:

```php
use ExtendsSoftware\ExaPHP\Cli\Output\StreamOutput;

$output = new StreamOutput(STDOUT, STDERR);
$output->write("Hello!\n");
$output->writeError("Something went wrong.\n");
```

The streams remain owned by the caller. Output writes at their current positions without seeking, flushing, or closing
them. Partial writes are completed; a write that makes no progress fails rather than waiting for a nonblocking stream.
Pass resources, not filenames or stream URIs. Invalid or closed destinations raise
`Output\Exception\InvalidOutputStreamException`; failed writes raise `Output\Exception\OutputWriteException`.
Both implement `CliException`, and write failures may leave partial output in the destination.

Handlers return a status and must not terminate the process. `ExitCode` is an integer-backed enum with `Success` (0),
`Failure` (1), and `InvalidUsage` (2). Return a case's `->value` from a handler. The return type remains `int` so applications
can define additional codes. Execution exceptions and engine errors can propagate to the caller. CLI handlers are
separate from CQRS handlers: they adapt terminal input and output around application operations rather than introducing
terminal concerns into domain logic.

## Define command input

Definitions describe a command without resolving its handler or parsing input:

```php
use ExtendsSoftware\ExaPHP\Cli\Definition\ArgumentDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\ArgumentMode;
use ExtendsSoftware\ExaPHP\Cli\Definition\CommandDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\OptionDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\OptionMode;

$definition = new CommandDefinition(
    'articles:create',
    'article.create.handler',
    'Create an article',
    arguments: [
        new ArgumentDefinition('title', 'Article title'),
        new ArgumentDefinition('summary', 'Optional summary', ArgumentMode::Optional),
    ],
    options: [
        new OptionDefinition('author', 'Author identifier', OptionMode::RequiredValue, 'a'),
        new OptionDefinition('publish', 'Publish immediately'),
    ],
);
```

`CommandDefinition` retains the handler service identifier and ordered lists of arguments and options. Its description
and the descriptions on individual entries supply help metadata. Constructing definitions does not access services.

Names are case-sensitive ASCII names starting with a letter and continuing with letters, digits, or hyphens. Command
names additionally allow colon-separated segments, each starting with a letter. Short aliases are single ASCII letters;
do not include leading hyphens in option names or aliases.

Arguments default to `ArgumentMode::Required`; optional arguments use `ArgumentMode::Optional`. Required arguments must
precede optional ones. Options default to `OptionMode::Flag`. `RequiredValue` means that a supplied option needs a value;
it does not require the option itself to appear.

Argument names, long option names, and short aliases must each be unique within their own group. An argument and an
option may share a name, and long names and short aliases are distinct. Argument and option collections must be lists.
Invalid definitions throw the respective `InvalidCommandDefinitionException`, `InvalidArgumentDefinitionException`, or
`InvalidOptionDefinitionException` under `Cli\Definition\Exception`; each implements `CliException`.

## Parse command arguments

`Input\Parser\ArgvInputParser` implements `InputParser`. Pass the selected definition and shell-tokenized arguments,
excluding the script and command name:

```php
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\ArgvInputParser;

// $definition is the articles:create definition above.
$input = new ArgvInputParser()->parse($definition, ['My article', '--author=42', '--publish']);
// arguments: ['title' => 'My article']
// options: ['author' => '42', 'publish' => true]
```

Options may appear before or after positional arguments. Long valued options accept `--author=42` or `--author 42`;
short aliases accept `-a 42`. Flags accept `--publish` or `-p`, without a value. Short bundles (`-pa`), attached short
values (`-a42`), and short equals syntax (`-a=42`) are rejected. Names are case-sensitive.

`--` ends option parsing, making every subsequent token positional. A lone `-` is also positional. Values starting with
hyphens must use long equals syntax, such as `--author=-42`; a separate lone `-` is accepted as an option value.
A following option or `--` is never consumed as a missing option's value. Explicit empty strings and text containing
spaces or quotes are preserved. The shell has already processed quoting; the parser does not split tokens again.

Flags are true when supplied. Omitted optional arguments and options remain absent. Repeated occurrences are rejected,
including a long name followed by its alias. Required positional arguments must be supplied and excess positional
arguments are errors. The parser validates structure only, preserving all valued arguments and options as strings.
Each call is independent and does not resolve handlers or read process globals.

Failures under `Cli\Input\Parser\Exception` implement `CliException`:

| Exception | Cause |
| --- | --- |
| `InvalidTokensException` | Tokens are not a list of strings. |
| `UnknownOptionException` | An undeclared option or unsupported short syntax is supplied. |
| `MissingOptionValueException` | A valued option has no unambiguous value. |
| `UnexpectedOptionValueException` | A flag receives an equals value. |
| `DuplicateOptionException` | The same option is supplied more than once. |
| `MissingArgumentException` | A required positional argument is absent. |
| `UnexpectedArgumentException` | Too many positional arguments are supplied. |

## Register and dispatch commands

`Routing\RegisteredCommands` implements `CommandRegistry`. Supply a list of definitions; lookup uses exact,
case-sensitive names and `all()` returns definitions in registration order. Duplicate names raise
`Routing\Exception\DuplicateCommandException`. Malformed registration lists raise `InvalidCommandRegistrationException`
in that same namespace. Missing commands raise `CommandNotFoundException`.

```php
use ExtendsSoftware\ExaPHP\Cli\Routing\CommandDispatcher;
use ExtendsSoftware\ExaPHP\Cli\Routing\RegisteredCommands;

$registry = new RegisteredCommands([$definition]);
// $resolver implements Cli\Handler\HandlerResolver; $output implements Cli\Output\Output.
$dispatcher = new CommandDispatcher($registry, new ArgvInputParser(), $resolver);
$exitCode = $dispatcher->dispatch('articles:create', ['My article', '--author=42'], $output);
```

`CommandDispatcher` selects the definition, parses its tokens, and only then resolves its handler identifier through
`Handler\HandlerResolver`. Unknown commands and invalid usage never resolve handlers. The selected handler receives
parsed input and the supplied output object; its integer exit code is returned unchanged. Lookup and parsing do not
resolve any other command's handler.

Resolver implementations must return a `CommandHandler` or throw `Handler\Exception\HandlerResolutionException`.
Dispatch failures propagate unchanged, including application exceptions and engine errors. The dispatcher does not
print error messages or terminate the process; its caller owns those policies. Registry listing can supply command
metadata without constructing or executing handlers.

## Run an application

Use `Integration\Cli\CliModule` for service locator wiring and `CliRunner` to bootstrap, dispatch, and shut down one
application lifecycle. See the [CLI integration guide](../integration/README.md#register-and-run-cli-commands) for command
configuration and a `bin/console.php` entry point.

## Render help

`Help\HelpRenderer` renders plain text from definitions without resolving handler services:

```php
use ExtendsSoftware\ExaPHP\Cli\Help\HelpRenderer;

$renderer = new HelpRenderer();
$output->write($renderer->commandList($registry->all(), 'bin/console.php'));
$output->write($renderer->commandHelp($registry->get('greet'), 'bin/console.php'));
```

Listings retain registration order. Usage marks required arguments as `<name>`, optional arguments as `[name]`, and
valued options as `--name <value>`. Output includes descriptions and short aliases and ends with a newline.
`CliRunner` handles [help invocations](../integration/README.md#display-cli-help) before command parsing and execution.

## Present errors

`ErrorHandling\ExceptionPresenter` writes failures to stderr and selects a nonzero exit code.
`DefaultExceptionPresenter` shows `UsageException` messages with `ExitCode::InvalidUsage->value`; all other failures
produce a generic message and `ExitCode::Failure->value`. Parser and command lookup exceptions implement
`UsageException`. Implement that contract only for invalid usage with messages suitable for terminal output.

The presenter does not print traces, log exceptions, or exit the process. Output failures propagate unchanged.
Use the [CLI error boundary](../integration/README.md#present-cli-failures) to present failures after application cleanup,
including bootstrap failures, or inject a custom presenter to add application logging and formatting.

## Run the outbox worker

The optional [Outbox integration module](../outbox/README.md#run-the-cli-worker) registers `outbox:work` using this CLI
infrastructure. It processes messages within one application lifecycle, supports `--once`, and stops cooperatively on
SIGTERM or SIGINT. Use the CLI exception boundary to report worker failures and return a nonzero exit code.
