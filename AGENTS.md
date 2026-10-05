# AGENTS.md

## Project

- This project targets PHP 8.5.
- Use modern PHP features where they improve clarity, type safety, or maintainability.
- Keep implementations small, explicit, and easy to reason about.
- Prefer simple designs over unnecessary abstraction.
- Respect existing package boundaries and dependency direction.
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
- Avoid magic behavior unless it is an intentional framework feature.
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

- Organize directories and namespaces by domain or framework concept rather than by PHP language construct.
- Nest by concept, not by class.
- Keep only the primary component contract and types that belong directly to the component at the component root.
- Place contracts for nested concepts inside the namespace of the concept they define.
- Do not place a type at the component root merely because it is an interface, abstraction, or shared contract.
- An interface and the implementations intrinsic to that interface should normally share the same conceptual namespace.
- For example, `ServiceLocator` belongs to `ServiceLocator`, while `ServiceResolver`, `FactoryServiceResolver`, and
  `InvokableServiceResolver` belong to `ServiceLocator\Resolver`.
- Group related secondary concepts under descriptive namespaces such as `Resolver`, `Exception`, `Factory`,
  `Middleware`, or `Attribute`.
- Create a nested namespace only when it represents a meaningful sub-concept with multiple related types or a clear
  expectation of growth.
- Do not create a directory merely because a class name contains multiple words.
- Prefer shallow structures initially and introduce additional namespace levels only when they clarify ownership or
  separate a meaningful sub-concept.
- Keep interfaces together with the concept they define. Do not create generic `Interface`, `Contract`, or
  `Implementation` directories solely to separate language constructs.
- Keep implementations close to their abstraction when those implementations are intrinsic to the same component or
  concept.
- Use namespaces to communicate conceptual ownership. A type placed within a namespace must clearly belong to that
  concept.
- Avoid catch-all namespaces or directories such as `Util`, `Helper`, `Common`, or `Misc`.
- Directory structure must correspond exactly to the PSR-4 namespace structure.
- Do not introduce additional namespace levels that provide no meaningful architectural distinction.

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

- Each package must depend only on components required for its own core responsibility.
- Do not introduce dependencies merely for convenience.
- Prefer PHP standard library functionality when it is sufficient.
- Keep package dependency direction explicit.
- Avoid circular package dependencies.
- Treat package boundaries as architectural boundaries.
- Before adding a dependency, consider whether composition at a higher layer is more appropriate.

For example, an HTTP package should not automatically depend on a validation package merely because validation is
commonly used together with HTTP.

## PHPDoc

- Add complete PHPDoc to all public classes, interfaces, traits, enums, methods, properties, and constants.
- Add PHPDoc to every non-promoted property, regardless of visibility.
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
 * Resolves a service by its identifier.
 *
 * The configured resolver chain is evaluated until a resolver capable of
 * resolving the requested service is found. The current resolution context
 * is reused for nested resolutions so circular dependencies can be detected.
 *
 * @param non-empty-string $id The service identifier.
 *
 * @return object The resolved service instance.
 *
 * @throws ServiceNotFoundException When no resolver can resolve the service.
 */
public function get(string $id): object
{
}
```

Simple methods must still be documented completely.

Example:

```php
/**
 * Returns the service identifier.
 *
 * @return non-empty-string The service identifier.
 */
public function id(): string
{
    return $this->id;
}
```

## Error Handling

- Each component must define its own root exception interface.
- The root exception interface must extend `Throwable`.
- Name the root exception interface after the component, for example `ServiceLocatorException`.
- Every component-specific exception must implement the component root exception interface.
- Specific exceptions should extend the most appropriate SPL exception type, such as `InvalidArgumentException`,
  `LogicException`, or `RuntimeException`, while also implementing the component root exception interface.
- Use specific exception types for distinct failure conditions.
- Callers must be able to catch either a specific exception or the component root exception.
- Do not use generic `Exception` or `RuntimeException` directly for component-specific failures when a more specific
  exception can be defined.
- Exceptions should represent exceptional situations rather than normal control flow.
- Catch exceptions as narrowly as possible.
- Do not catch `Throwable` unless errors such as `TypeError` and other engine-level failures are intentionally part of
  the recovery or translation behavior.
- Do not wrap an exception merely to replace it with another exception to the same meaning.
- Wrap an exception only when the current abstraction adds meaningful context, translates a lower-level failure into the
  component's public exception contract, or changes the semantic meaning of the failure.
- When wrapping an exception, preserve the original exception as the previous exception.
- A wrapping exception message must add useful context that is not already obvious from the original exception.
- Avoid repeated wrapping across multiple layers when each layer adds no meaningful information.
- Prefer propagating an existing component exception unchanged when it already accurately represents the failure at the
  current abstraction level.
- Do not expose lower-level implementation exceptions through a public component API when those exceptions are not part
  of that API's documented contract.
- Exception messages must be clear and useful for debugging.
- Document publicly relevant exceptions using `@throws`.

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

- Use end-to-end tests to verify complete externally observable flows through the application or framework.
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
  `ServiceLocatorTest`.
- Name integration test classes using the `IntegrationTest` suffix, for example `ServiceLocatorIntegrationTest`.
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
- Summarize a newly introduced component in one entry unless separate capabilities warrant independent mention.
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
    - new framework capabilities;
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

- Initial ExaPHP 2.0 project structure.
- Composer package configuration requiring PHP 8.5.
- PSR-4 autoloading for the `ExtendsSoftware\ExaPHP` namespace.
- MIT license.
- Initial project documentation.
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
- Update `README.md` files when installation, setup, public APIs, requirements, or usage change.
- Keep the root `README.md` focused on project purpose, status, requirements, setup, a minimal usage example, and
  testing.
- Summarize available components briefly and link to their detailed documentation.
- Place detailed component usage, configuration, extension points, exceptions, and edge cases in dedicated
  documentation.
- Avoid duplicating API reference material or internal architectural explanations in the root `README.md`.
- When functionality changes, update documentation at the appropriate level; do not automatically expand the
  `README.md`.
- Prefer explaining intent and behavior rather than merely restating implementation details.
- Write component documentation as a usage guide with relevant constraints, not a prose copy of the source or PHPDoc.
- Keep each paragraph useful for configuring, using, extending, or troubleshooting the component. Remove material that
  serves none of these purposes.
- Organize guides around user tasks: basic usage first, then relevant constraints, error handling, and extension points.
- Explain guarantees and limitations that affect user decisions. Keep internal algorithms and architectural rationale
  in code documentation unless users need them to extend the component.
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
