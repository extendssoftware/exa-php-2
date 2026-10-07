# Changelog

All notable changes to ExaPHP will be documented in this file.

## [Unreleased]

### Added

- Clock component with a current-time contract, a UTC system clock, an immutable frozen clock for deterministic tests,
  and a dedicated integration module registering the shared clock service.
- Messaging component with immutable JSON message envelopes, a durable publisher contract, explicit subscription
  definitions and exact-type lookup with unique subscriber identities, subscriber processing and resolution contracts,
  a consumer contract for receiving, acknowledgement, retries scheduled by absolute timestamp, and rejection, with
  immutable received deliveries, opaque receipts and acquisition counts, and component exceptions. A consumer processor
  resolves subscribers and records outcomes using an independent retry policy for subscriber exceptions and engine
  errors, with a fixed-delay implementation and bounded attempts.
  Includes lazy service-locator resolution through explicit subscriber mappings and an Outbox delivery bridge preserving
  envelope fields and translating publishing failures. An opt-in Messaging module configures subscriptions and provides
  `messaging:consume` with idle polling, `--once`, graceful shutdown, and CLI error reporting. Applications supply
  transport adapters and the consumer retry policy.
- Outbox component with immutable JSON message envelopes, a transactional writer contract, and processing contracts
  for exclusive claims with worker-selected lease durations and store-owned time evaluation, completion, scheduled
  retries, and terminal failure. Includes immutable claim values, a processor for one delivery attempt per invocation,
  delivery and retry contracts with full claim context for retry decisions, and a fixed-delay policy with bounded
  attempts. Includes an opt-in `outbox:work` CLI integration with configurable lease and idle polling durations,
  `--once`, and graceful SIGTERM/SIGINT shutdown through PCNTL. Applications supply persistence and delivery adapters;
  producer writes participate in the application's transaction. Development dependencies require PCNTL for signal
  tests; the development container enables it.
- Transaction component with a generic transactional execution contract, explicit nested-call rejection, lifecycle
  exceptions preserving operation and rollback failures, and opt-in CQRS command middleware.
- CLI component with command definitions, argument and option parsing, lazy handler dispatch, stream output, named exit
  codes, command listings, definition-based usage help, and customizable error presentation with usage and failure
  codes. Includes reusable worker control for cooperative shutdown and idle waiting, a PCNTL signal implementation,
  and a shared control service registered by the CLI integration module.
- HTTP component with immutable request, response, header, and URI values, named method and status enums, repeatable
  string bodies, body and handler contracts, ordered middleware execution, and method/path routing with typed matches,
  immutable request attributes, required unique route names, an explicit shared route collection for router
  construction, and encoded URL generation, lazy handler resolution with a service locator adapter, and 404/405
  responses. Includes single-use stream bodies, PHP request creation and response emission adapters, and
  exception-handling middleware with customizable response factories and a generic 500 default. Immutable RFC 9457
  Problem Details and JSON response creation provide default 400/404/405/406/413/415/500 error bodies with safe generic
  titles. Named module `ExceptionProblemDetailsMapper` services compose in configuration order, rendering the first
  match before framework fallbacks. Content negotiation selects registered response factories using Accept preferences,
  with JSON as the configurable default and 406 for unsupported formats. Content-type-selected request decoding supports
  bounded JSON input and maps decoding failures to 400, 413, and 415. Requires PHP’s native `ext-uri` extension.
- Processing component with transformation, validation, and pipeline contracts, sequential execution with unambiguous
  step roles, immutable shared violations and results, null, string, pattern, strict-membership, and integer-range
  validators, collecting `AllOf` validation, string trimming and integer conversion, and nested object/array shapes and
  collection processing with explicit field policies and prefixed violation paths.
- Logging component with logger, writer, and formatter contracts, stream output, predicate filtering, multi-destination
  delivery with aggregated failures, immutable log records, and NDJSON formatting with UTC timestamps and inline
  exception details, severity enum, component exceptions, injectable clocks for record timestamps, and application
  integration with configurable writer and clock services, without PSR dependencies.
- DDD component with aggregate and domain event contracts, optional abstract aggregate roots, a reusable recorded-event
  collection, composable generic specifications, and an article example. Documents atomic command and synchronous
  listener execution, with application-provided transactional outboxes for deferred work and external side effects.
- Event component with event and listener contracts, synchronous dispatch to ordered listener lists, validated
  registrations, and component exceptions.
- PHP 8.5 CLI development container and just recipes for dependency installation and full or targeted test runs.
- GitHub Actions workflow running the full test suite through the shared just recipes on pushes and pull requests.
- Integration component with CQRS, Event, HTTP, and CLI modules providing default bus, dispatcher, and server services,
  named HTTP route and middleware configuration with lazy handler resolution, an HTTP application runner with shutdown
  and preservation of execution and cleanup failures, a CLI runner with command help, dispatch, shutdown, and an
  optional exception-handling boundary, configuration-driven factories, handler and middleware configuration under
  `cqrs.command` and `cqrs.query`, keyed listener registrations, and a root exception contract.
- CQRS component with command and generic query contracts, synchronous command and query buses with validated handler
  registrations, command and query middleware with extensible dispatch context, and component exceptions. Custom bus
  implementations must accept the optional context argument; existing dispatch calls remain valid.
- Application bootstrap with explicit module registration, optional module configuration, read-only configuration with
  dot-path lookup, application global and local overrides, service conflict detection, and service locator creation with
  configuration injection. Optional module bootstrap and shutdown hooks support ordered startup, reverse-order cleanup,
  and preservation of hook failures. Successful bootstrap is shared while running; distribution templates are excluded.
- Service locator component with immutable definitions for instances, aliases, factories, and class construction;
  generic PHPDoc return typing for class and interface lookups; extensible resolvers; constructor injection; shared
  service instances; circular dependency detection; and a component-specific exception contract.
- Initial ExaPHP 2.0 project structure with Composer configuration requiring PHP ^8.5 and PSR-4 autoloading for the
  `ExtendsSoftware\ExaPHP` namespace.
- MIT license.
- Initial README with requirements, development setup, and testing instructions.
