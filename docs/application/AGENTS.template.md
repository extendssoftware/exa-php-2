# AGENTS.md

## Project

- This application targets PHP 8.5 and uses ExaPHP.
- Treat the installed framework version and application Composer configuration as the source of supported APIs.
- Use modern PHP features where they improve clarity, type safety, or maintainability.
- Keep implementations small, explicit, and easy to reason about.
- Prefer simple designs over unnecessary abstraction.
- Respect application module boundaries and dependency direction.
- Do not introduce new dependencies unless they provide clear value.

## PHP

- Every PHP file must start with:

  ```php
  <?php

  declare(strict_types=1);
  ```

- Use native PHP types wherever possible.
- Prefer constructor property promotion when it improves clarity.
- Prefer `final` classes by default.
- Prefer `readonly` classes or properties when state should not change after construction.
- Prefer immutable objects where practical.
- Prefer constructor injection for dependencies.
- Prefer composition to inheritance.
- Use interfaces when an explicit abstraction boundary or multiple implementations are required.
- Prefer enums over classes containing constants when representing a finite set of meaningful values.
- Use backed enums when those values have a string or integer representation. Use `->value` at scalar boundaries;
  an API accepting additional application-defined scalar values does not require a constants class.
- Keep class constants for implementation settings, bit flags, or unrelated constants that do not form an enum.
- Prefer attributes to annotation-based metadata where PHP provides native attribute support.
- Add `#[Override]` to every class or enum method that implements an interface method or overrides an inherited
  non-private method in production code. Import `Override` explicitly.
- Test code, including test doubles, fixtures, and lifecycle methods, is exempt from the `#[Override]` requirement.
- Do not add `#[Override]` to constructors, new methods, or interface declarations introducing methods.
- Prefer `match` expressions over complex `switch` statements when appropriate.
- Prefer first-class callables over equivalent closures when no additional closure logic is required.
- Prefer early returns over deeply nested conditionals.
- Avoid setters where explicit behavior or immutable replacement is clearer.
- Use framework behavior explicitly; avoid application magic that hides dependencies or side effects.
- Do not use deprecated PHP functionality.

## Code Style

- Follow PSR-12 unless this document defines a stricter rule.
- Use one class, interface, trait, or enum per file.
- Use explicit and descriptive names.
- Name classes, interfaces, traits, and enums after their intent rather than their language construct. Do not use
  suffixes such as `Interface`; use `ServiceResolver` instead of `ServiceResolverInterface`.
- Use 120 characters as the standard maximum line length.
- Do not wrap lines at 80 characters.
- Keep a line on a single line when it fits within 120 characters.
- Wrap lines only when they would exceed 120 characters or when a logical line break clearly improves readability.
- Prefer natural line breaks at logical boundaries rather than mechanically wrapping text to a shorter width.
- Apply the same 120-character line-length rule to PHP, PHPDoc, Markdown, configuration files, and test code where
  practical.
- Keep classes focused on a single responsibility.
- Keep methods small and focused.
- Avoid unnecessary boolean parameters when a more expressive type, enum, or separate method can be used.
- Prefer passing an existing domain or value object when it represents the concept an operation acts on, rather than
  extracting individual properties at the call site.
- Pass scalar values when they fully express the required input or preserve a meaningful dependency boundary.
- Do not pass whole objects solely for speculative future needs.
- Avoid hidden side effects.
- Do not suppress errors, warnings, or static-analysis findings without a documented reason.
- Prefer explicit behavior over clever or overly compact code.
- Remove dead code instead of commenting it out.
- Keep nested function and constructor calls on a single line when the complete expression fits within 120 characters
  and remains easy to read.
- When an outer call spans multiple lines, keep nested calls compact when they remain short and readable.
- When a nested call itself becomes long or complex, place it on its own indentation level.
- Prefer vertical structure only when it improves readability; do not expand nested calls mechanically.
- Import referenced classes, interfaces, traits, enums, functions, and constants explicitly with `use`, `use function`,
  and `use const` statements when they are used from another namespace.
- Do not rely on fully qualified function or constant names inline when an import keeps the code clearer.
- Keep imports grouped by kind: classes/interfaces/traits/enums first, then functions, then constants.
- Remove unused imports.

## Project Structure

- Organize application code by business module or bounded context, such as Article, Account, or Billing.
- Keep each module's use cases, business rules, adapters, configuration, and tests clearly owned by that module.
- Follow the existing module layout. Do not create empty layers or directories merely to follow a template.
- Separate domain behavior, application orchestration, and infrastructure where those responsibilities exist.
- Domain code owns aggregates, entities, value objects, business invariants, and domain events.
- Application code owns command/query handlers, use-case orchestration, and application authorization services.
- Infrastructure code owns persistence, messaging transports, external-service clients, and framework wiring.
- HTTP and CLI adapters translate transport input into application operations and translate results for their callers.
- Keep repository contracts with the domain or application concept that consumes them; place concrete database
  implementations in infrastructure. Read-model contracts may belong to the query use case.
- Keep interfaces with the concepts they define. Do not create generic `Interface`, `Contract`, or `Implementation`
  directories solely to separate PHP language constructs.
- Nest by concept, not by class. Introduce namespace levels only when they clarify ownership or responsibilities.
- Use descriptive secondary namespaces such as `Command`, `Query`, `Exception`, `Middleware`, or `Persistence` when
  they represent meaningful concepts in the module.
- Avoid catch-all namespaces or directories such as `Util`, `Helper`, `Common`, or `Misc`.
- Share code across modules only when there is a real shared concept and a clear owner; avoid a growing shared directory
  that couples otherwise independent modules.
- Directory structure must correspond exactly to the application's configured PSR-4 namespaces.

## Framework Usage and Module Boundaries

- Use ExaPHP contracts and integration modules for the capabilities they provide. Check the installed API before using
  it.
- Implement application adapters and policies through framework extension points rather than duplicating framework code.
- Do not edit Composer-managed files in `vendor/`. Address framework defects through a dependency update or an explicit,
  documented application adapter when appropriate.
- Register services, routes, middleware, handlers, and subscribers through module configuration and application
  overrides.
- Keep service-locator access in bootstrap and service factories. Inject collaborators into handlers, policies,
  repositories, subscribers, and domain services.
- Let each module own its service registrations and public application contracts.
- Communicate across modules through explicit application contracts or events. Do not access another module's tables,
  repository implementation, or private domain internals directly.
- Prefer explicit dependencies to automatic discovery or naming conventions that conceal application wiring.
- Keep domain code independent of HTTP, CLI, service-location, and concrete persistence concerns. Small framework domain
  contracts and value types may be used when they express the domain without introducing infrastructure coupling.

## Application Use Cases

- Keep HTTP and CLI handlers thin: decode input, apply input validation, establish trusted context, and invoke use
  cases.
- Keep commands, queries, and result DTOs explicit and typed. Do not pass HTTP requests into domain or application
  logic.
- Command handlers coordinate loading aggregates, authorization, domain behavior, persistence, and event dispatch.
- Query handlers authorize access and return read models or output DTOs. Load aggregates only when the query needs them.
- Keep business invariants in aggregates or domain services so every entry point observes the same rules.
- Prefer domain behavior methods, such as `publish()`, over generic state setters.
- Pass already-loaded resources to authorization checks rather than loading them again solely for authorization.
- Use a presenter only when it performs meaningful mapping to an output DTO; HTTP serialization belongs at the HTTP
  boundary.
- Apply visibility and tenant restrictions to list/search queries before pagination and counting.
- Inject the framework clock when behavior depends on the current time, and use a deterministic clock in tests.

## Classes and Immutability

- Classes must be `final` unless inheritance is an intentional part of the public API.
- Use `readonly` whenever an object's state should not change after construction.
- Prefer immutable value objects.
- Prefer explicit domain or API methods over generic setters.
- Use constructor injection for required dependencies.
- Optional dependencies should be rare and must have a clear reason.
- Avoid service location unless implementing service-location infrastructure itself.
- Do not depend on global state when dependency injection is possible.

## Dependencies

- Each module must depend only on capabilities required for its own responsibility.
- Keep dependency direction explicit: infrastructure implements domain/application contracts, and application services
  orchestrate domain behavior. Domain code must not depend on infrastructure implementations.
- Avoid circular module dependencies. Use a coordinating application service or events when direct coupling is
  unsuitable.
- Prefer PHP standard library functionality and existing framework capabilities when sufficient.
- Do not introduce dependencies merely for convenience or duplicate a library already serving the same purpose.
- Keep third-party API types behind adapters when exposing them would couple business logic to a vendor.
- Review dependency changes for PHP compatibility, maintenance, and operational impact. Commit Composer metadata and
  lock-file changes together when updating application dependencies.

## PHPDoc

- For methods implementing an interface or overriding an inherited method, use a PHPDoc block containing only
  `{@inheritDoc}` when the inherited documentation fully describes the method.
- Do not repeat inherited documentation. Add explicit PHPDoc only when the implementation introduces relevant
  behavior, constraints, exceptions, or more precise types that the inherited documentation does not describe.
- PHPDoc blocks containing only `{@inheritDoc}` are exempt from the summary, `@param`, and `@return` requirements below.
- Keep complete contract documentation on the interface or parent method.
- Add complete PHPDoc to all public classes, interfaces, traits, enums, methods, properties, and constants.
- Add PHPDoc to every non-promoted property, regardless of visibility.
- Include an `@var` tag in every non-promoted property's PHPDoc, even when it repeats the native type. Use refined
  scalar types, generics, array shapes, lists, or callable signatures when they describe the property more precisely.
- Add PHPDoc to non-public methods and constants when it improves clarity or documents behavior that is not obvious
  from the implementation.
- PHPDoc may intentionally repeat information already expressed by PHP types when this provides a compact and complete
  API overview.
- Every PHPDoc block must start with a concise summary.
- Keep summaries short and factual.
- Add a longer description only when it adds meaningful information beyond the summary and signature.
- Keep longer descriptions concise. Prefer one short paragraph unless additional detail is necessary to explain
  behavior, intent, constraints, side effects, guarantees, edge cases, or architectural context.
- Do not add hypothetical behavior, speculative nuances, or implementation possibilities merely to make PHPDoc more
  comprehensive.
- Document only behavior that is intentionally part of the documented contract.
- Document interfaces in terms of their contract, responsibilities, guarantees, inputs, outputs, failure modes, and
  observable behavior.
- Interface PHPDoc must describe what implementations are required to provide, not how known implementations currently
  achieve it.
- Keep interface PHPDoc implementation-agnostic.
- Do not mention concrete implementations, implementation strategies, internal algorithms, storage mechanisms,
  infrastructure choices, or currently known subclasses in interface PHPDoc unless they are explicitly part of the
  public contract.
- Do not use examples of possible implementations to explain an interface unless those examples are necessary to define
  the contract.
- Do not turn behavior observed in existing implementations into an interface requirement unless that behavior is
  intentionally part of the contract.
- Do not document possible implementation-dependent behavior unless callers need to rely on that possibility as part of
  the public contract.
- Place implementation-specific behavior, constraints, algorithms, and architectural details on the concrete
  implementation that owns them.
- Document every parameter using `@param`.
- Document every return value using `@return`, including `void`, except constructors.
- Document relevant exceptions using `@throws`.
- Document generic types, array shapes, lists, refined scalar types, templates, and callable signatures where useful.
- Format literal configuration keys and paths as inline code in PHPDoc prose, for example `cli.commands` and
  `http.request.json.maxBytes`. Use backticks rather than ordinary quotation marks; apply the same convention in
  Markdown.
- Use inline code for literal code references in prose, such as service identifiers, class or method names, command
  names,
  and file paths, when distinguishing them from ordinary language. Keep PHPDoc tag types and parameter names unquoted
  so tools can parse tags such as `@param`, `@return`, and `@throws` normally.
- Keep PHPDoc synchronized with the implementation.
- Update PHPDoc whenever related behavior changes.
- Do not leave outdated or inaccurate documentation.
- Do not add inline PHPDoc blocks to promoted constructor properties.
- Document promoted properties through the constructor PHPDoc using `@param`.
- Add separate property PHPDoc only for non-promoted properties or when PHPDoc provides type information that cannot be
  expressed through the constructor parameter alone.
- Test code is exempt from the mandatory PHPDoc rules; follow the rules under `## Testing` instead.

Example:

```php
/**
 * Loads an article by its identifier.
 *
 * @param non-empty-string $id The article identifier.
 *
 * @return Article The stored article.
 *
 * @throws ArticleNotFoundException When the article does not exist.
 */
public function get(string $id): Article
{
}
```

Simple methods must still be documented completely.

Example:

```php
/**
 * Returns the article identifier.
 *
 * @return non-empty-string The article identifier.
 */
public function id(): string
{
    return $this->id;
}
```

## Sensitive Data

- Mark every HTTP `Request` parameter on concrete production methods and closures with `#[SensitiveParameter]`,
  including middleware, handlers, routing, decoding, and response factories, regardless of visibility.
- Import `SensitiveParameter` explicitly. Attributes on interface declarations do not protect implementation arguments;
  apply the attribute to each concrete implementation.
- Use the same protection for parameters receiving credentials or secrets.
- `SensitiveParameter` redacts arguments in stack traces only. Do not log or dump request headers, bodies, or credential
  values without explicit redaction; the attribute does not protect other references or explicit logging.

## Error Handling

- Each business module should define a root exception interface extending `Throwable`, such as `ArticleException`.
- Keep that interface at the module root and specific exceptions under the concept that owns the failure.
- Application-owned module exceptions must implement their module's root exception interface.
- Specific exceptions should extend the most appropriate SPL type, such as `InvalidArgumentException`, `LogicException`,
  or `RuntimeException`, while implementing the module exception contract.
- Use specific exception types for distinct failures. Do not use generic `Exception` or `RuntimeException` directly
  for application failures that need a meaningful public contract.
- Adapters implementing framework contracts must translate expected failures into the exceptions required by those
  contracts. Preserve lower-level causes without exposing infrastructure details in public responses.
- Keep domain exceptions independent of HTTP status codes, Problem Details, CLI output, and transport-specific metadata.
- Map application exceptions to safe HTTP Problem Details through the framework's exception mapping infrastructure.
- Distinguish permission denial, domain invariant failure, missing resources, and operational failure by their meaning,
  not merely by the layer in which they were thrown.
- Exceptions should represent exceptional situations rather than normal control flow.
- Catch exceptions as narrowly as possible.
- Do not catch `Throwable` unless errors such as `TypeError` and other engine-level failures are intentionally part of
  the recovery or translation behavior.
- Do not wrap an exception merely to replace it with another exception to the same meaning.
- Wrap an exception only when the current abstraction adds meaningful context, translates a lower-level failure into the
  module's or framework's public exception contract, or changes the semantic meaning of the failure.
- When wrapping an exception, preserve the original exception as the previous exception.
- A wrapping exception message must add useful context that is not already obvious from the original exception.
- Avoid repeated wrapping across multiple layers when each layer adds no meaningful information.
- Prefer propagating an existing module or framework exception unchanged when it already accurately represents the
  failure at the current abstraction level.
- Do not expose lower-level implementation exceptions through a public application API when those exceptions are not
  part of that API's documented contract.
- Exception messages must be clear and useful for debugging.
- Document publicly relevant exceptions using `@throws`.

## Authentication and Authorization

- Authentication verifies credentials and establishes an `Actor`; authorization evaluates that actor's permissions.
- An actor is an identity reference, not a loaded user entity or proof that authentication occurred.
- Obtain actors from successful authentication or trusted application entry points. Do not trust actor identifiers
  supplied directly in request bodies, headers, or message payloads as proof of identity.
- Attach required authentication middleware explicitly to protected routes or groups. Keep public endpoints outside
  protected groups and test both access paths.
- Application authenticators own token verification and account lookup; use established verification libraries rather
  than implementing cryptographic verification algorithms.
- Distinguish rejected credentials from operational verification failures. Failures must never grant access.
- Enforce authorization in application use cases, including command/query handlers, so checks also apply outside HTTP.
- Use focused application policies implementing `Authorizer`, optionally exposed through typed services such as
  `ArticleAuthorization`. Share permission lookup through injected services rather than global actor state.
- Deny unsupported actions or resource types. Include actor kind as well as identifier in identity comparisons.
- Keep resource permissions separate from aggregate invariants: permission to publish does not make an invalid article
  publishable. The aggregate must still enforce its business rules.
- Authentication and authorization failures must not expose credentials, private account details, or sensitive
  resources.

## Persistence and External Adapters

- Use repositories and read-model queries with explicit contracts; keep database queries out of transport handlers
  and domain entities.
- Parameterize queries, validate dynamic identifiers, and preserve tenant boundaries in reads and writes.
- Use optimistic concurrency checks or appropriate locking where concurrent updates could invalidate business decisions.
- Keep schema changes in versioned migrations. Consider existing data, deployed workers, and rolling deployments when
  changing schemas or message formats.
- Keep transactions scoped to the atomic application operation. Do not wrap worker lifecycles or external network calls
  in long-lived database transactions.
- Configure timeouts for external calls. Add retries only where failure semantics and idempotency make them safe.
- Integration-test adapters against the actual infrastructure they implement, including rollback, concurrency, and
  ambiguous outcomes where relevant.

## Transactions and Events

- Treat a command and its synchronous domain-event listeners as one atomic operation.
- Dispatch recorded domain events after saving the aggregate and before committing the transaction.
- Synchronous listeners must propagate failures so the enclosing transaction can roll back.
- All persistence performed by synchronous listeners must participate in the enclosing transaction.
- Route deferred work and external side effects through a transactional outbox.
- Persist outbox messages in the same transaction as the aggregate changes.
- Process committed outbox messages asynchronously, with retries and duplicate-delivery handling.
- Use the configured transactional command middleware for command transaction ownership. Do not start nested
  transactions from handlers or synchronous listeners.
- Keep transaction management, synchronous event dispatch, and asynchronous delivery as separate responsibilities.

## Asynchronous Processing

- Use Outbox for reliable publication of work recorded with application changes, and Messaging for subscriber delivery.
- Configure application persistence/transport adapters, subscriber mappings, and retry policies explicitly.
- Subscribers must tolerate duplicate delivery. Protect non-idempotent side effects with durable deduplication or an
  equivalent application guarantee.
- Treat acquisition attempts, retry timing, stale ownership, and terminal failure as part of the adapter contract.
- Choose retry policies according to application failure semantics; do not endlessly retry permanent failures.
- Let subscribers propagate failures so processors can apply their configured retry or rejection behavior.
- Version durable message types and payloads when compatibility changes. Consider messages already queued at deployment.
- Keep actor context explicit in asynchronous operations. Decide whether execution uses a system actor or a verified
  initiating actor, and whether current authorization must be reevaluated.
- Use worker commands and process supervision for lifecycle management. Shared services must not retain mutable
  per-request, per-actor, or per-message state between operations.

## Configuration and Operations

- Keep deployment-specific settings outside business logic and apply them through application configuration.
- Do not commit real secrets, credentials, tokens, or production personal data. Provide safe example configuration.
- Validate required configuration and fail with useful, redacted diagnostics when dependencies cannot be initialized.
- Preserve request/message correlation identifiers across application boundaries when available.
- Log operational context without dumping requests, credentials, or sensitive domain objects.
- Keep health checks, migrations, and maintenance commands scoped to their operational purpose; do not add business
  side effects to readiness checks or application bootstrap.
- Document required services, extensions, worker processes, environment settings, and deployment steps.

## Testing

### General

- Add or update tests for observable behavior changed by an implementation.
- Prefer testing public behavior over implementation details.
- Keep tests deterministic and independent.
- Tests must not depend on execution order.
- Keep test names descriptive and behavior-focused.
- Use data providers when they improve readability or reduce meaningful duplication.
- Prefer small real implementations or purpose-built test doubles over excessive mocking.
- Update tests together with implementation changes.
- Add regression tests for bug fixes when practical.
- Test exceptional behavior explicitly when exceptions are part of the public contract.
- Use PHPUnit 13 APIs and conventions.
- Do not use deprecated PHPUnit methods, attributes, assertions, configuration options, or other APIs.
- Before using an unfamiliar PHPUnit API, verify that it is available and not deprecated in the minimum supported
  PHPUnit version.
- Prefer the current documented PHPUnit API over legacy equivalents.
- Treat PHPUnit deprecation warnings as issues that must be fixed rather than ignored.

### Unit Tests

- Use unit tests for isolated behavior of individual classes, value objects, services, and other small units.
- Unit tests must not require databases, HTTP servers, filesystems, containers, or other external infrastructure.
- Prefer real collaborators when they are small, deterministic, and inexpensive to construct.
- Use test doubles only when isolation is necessary or a real collaborator would make the test unnecessarily complex.
- Do not mock the class under test.
- Do not test private or protected methods directly.
- Test behavior through the public API.

### Integration Tests

- Use integration tests to verify collaboration between multiple components or infrastructure adapters.
- Integration tests may use real infrastructure such as databases, filesystems, containers, or framework wiring when
  that infrastructure is part of the behavior being tested.
- Keep the scope of an integration test focused on a specific integration boundary.
- Do not use integration tests where a unit test provides the same confidence with less complexity.

### End-to-End Tests

- Use end-to-end tests to verify complete externally observable flows through the application.
- Exercise the system through its public entry points, such as HTTP or CLI interfaces.
- Keep end-to-end tests focused on critical behavior and integration paths.
- Do not duplicate every unit- or integration-level scenario as an end-to-end test.

### Test PHPDoc

- Do not add PHPDoc to test classes or test methods.
- Test names, setup, and assertions must make the tested behavior clear without PHPDoc.
- Add PHPDoc in tests only when required to express type information that PHP cannot represent, such as generics, array
  shapes, lists, templates, callable signatures, or data-provider shapes.
- Apply normal PHPDoc rules to reusable test infrastructure when it is not itself a test case.

### Test Naming

- Name unit test classes after the class or behavior under test using the `Test` suffix, for example
  `PublishArticleHandlerTest`.
- Name integration test classes using the `IntegrationTest` suffix, for example `ArticleRepositoryIntegrationTest`.
- Name end-to-end test classes using the `E2ETest` suffix, for example `HttpApplicationE2ETest`.
- Do not use prefixes such as `Integration` or `E2E` in test class names.
- Keep the subject under test at the beginning of the class name.
- Use descriptive test method names that state the behavior or expected outcome.
- Do not encode implementation details in test names unless those details are part of the behavior being verified.

## CHANGELOG.md

- Maintain `CHANGELOG.md` for notable changes.
- Use an `[Unreleased]` section for changes that have not yet been released.
- Use Keep a Changelog-style sections where applicable:
    - `Added`
    - `Changed`
    - `Deprecated`
    - `Removed`
    - `Fixed`
    - `Security`
- Add only notable user-facing, API-facing, compatibility-related, architectural, or project-level changes.
- Do not treat `CHANGELOG.md` as a replacement for Git history.
- Describe the net change since the previous release, not the sequence of development steps or commits.
- Before adding an entry, check whether an existing unreleased entry should be updated or consolidated.
- Summarize a newly introduced application capability in one entry unless separate capabilities warrant independent
  mention.
- Fold revisions to unreleased functionality into its existing entry. Use `Changed`, `Fixed`, or `Removed` when
  describing differences from previously released behavior.
- Do not add separate entries for tests accompanying a feature.
- Do not add routine maintenance changes unless they materially affect users or contributors.
- Usually do not add:
    - formatting-only changes;
    - minor refactoring without observable behavior changes;
    - routine test maintenance;
    - internal CI maintenance;
    - `.gitignore` changes;
    - dependency lock-file updates without meaningful impact;
    - repository housekeeping.
- Add changes such as:
    - new public APIs;
    - new application capabilities;
    - breaking changes;
    - changed public behavior;
    - compatibility changes;
    - minimum PHP version changes;
    - added or removed package dependencies when relevant;
    - deprecations;
    - bug fixes;
    - security fixes;
    - significant architectural changes users or contributors should know about.
- Write changelog entries from the perspective of a user or contributor, not as implementation notes.
- Keep entries concise and specific.
- Update `CHANGELOG.md` as part of the same change that introduces the notable modification.

Example:

```md
## [Unreleased]

### Added

- Article publication workflow with authorization and deferred subscriber processing.
- Protected HTTP endpoints for article management.
- Database migrations and worker deployment instructions.
```

Avoid entries such as:

```md
- Updated `.gitignore`.
- Reformatted PHP files.
- Renamed local test helper.
- Updated CI cache configuration.
```

unless those changes have meaningful impact outside normal repository maintenance.

## Documentation

- Keep documentation synchronized with the implementation.
- Always present documented items in the same order as the corresponding source listing or structure. Module lists
  must follow the alphabetical directory order in `src/`; API and configuration lists must follow their declarations.
- Keep this ordering consistent across documentation files and update it when the corresponding source order changes.
- Update `README.md` files when installation, setup, public APIs, requirements, or usage change.
- Keep the root `README.md` focused on project purpose, status, requirements, setup, a minimal usage example, and
  testing.
- Summarize application modules briefly and link to their detailed documentation.
- Place detailed module usage, configuration, extension points, exceptions, and edge cases in dedicated
  documentation.
- Avoid duplicating API reference material or internal architectural explanations in the root `README.md`.
- When functionality changes, update documentation at the appropriate level; do not automatically expand the
  `README.md`.
- Prefer explaining intent and behavior rather than merely restating implementation details.
- Write application documentation as a usage guide with relevant constraints, not a prose copy of the source or PHPDoc.
- Keep each paragraph useful for configuring, using, extending, or troubleshooting the application. Remove material that
  serves none of these purposes.
- Organize guides around user tasks: basic usage first, then relevant constraints, error handling, and extension points.
- Explain guarantees and limitations that affect user decisions. Keep internal algorithms and architectural rationale
  in code documentation unless users need them to extend the application.
- Explain each behavior once in its most relevant section and link to it rather than repeating it.
- Use small, purposeful examples. State any application-specific prerequisites and demonstrate extension points with
  an example or a link to a complete implementation when useful.
- Document implemented behavior only. Keep proposed designs, development history, and lists of absent internals out of
  usage guides.
- Review existing documentation when updating it: consolidate repetition and remove stale material rather than merely
  appending another paragraph for each change.
- Examples must use the currently supported API.
- Remove obsolete documentation when functionality is removed.

## Changes

- Keep changes focused on the requested task.
- Do not perform unrelated refactoring unless required to implement the requested change correctly.
- Preserve backward compatibility unless a breaking change is intentional.
- When introducing a breaking change, update:
    - implementation;
    - PHPDoc;
    - tests;
    - documentation;
    - `CHANGELOG.md`.
- Update related documentation and tests in the same change.
- Do not leave temporary implementations, commented-out code, or unfinished TODOs unless explicitly required.
