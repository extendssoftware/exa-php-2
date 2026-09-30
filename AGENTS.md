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
- Prefer enums for finite sets of meaningful values.
- Prefer attributes to annotation-based metadata where PHP provides native attribute support.
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
- Use a maximum line length of 120 characters.
- Wrap lines when exceeding 120 characters, except where wrapping would reduce readability.
- Prefer natural line breaks at logical boundaries rather than mechanically filling the available width.
- Keep classes focused on a single responsibility.
- Keep methods small and focused.
- Avoid unnecessary boolean parameters when a more expressive type, enum, or separate method can be used.
- Avoid hidden side effects.
- Do not suppress errors, warnings, or static-analysis findings without a documented reason.
- Prefer explicit behavior over clever or overly compact code.
- Remove dead code instead of commenting it out.

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
- Add PHPDoc to non-public members when it improves clarity or documents behavior that is not obvious from the
  implementation.
- PHPDoc may intentionally repeat information already expressed by PHP types.
- Repetition is acceptable when it provides a compact and complete overview of the API.
- Every PHPDoc block must start with a concise summary.
- Add a longer description that explains what the element does.
- Use longer descriptions to document behavior, intent, constraints, side effects, guarantees, edge cases, or
  architectural context.
- Document every parameter using `@param`.
- Document every return value using `@return`, including `void`.
- Document relevant exceptions using `@throws`.
- Document generic types, array shapes, lists, refined scalar types, templates, and callable signatures where useful.
- Keep PHPDoc synchronized with the implementation.
- Update PHPDoc whenever related behavior changes.
- Do not leave outdated or inaccurate documentation.

Example:

```php
/**
 * Resolves a service by its identifier.
 *
 * The configured resolver chain is evaluated until a resolver capable of
 * resolving the requested service is found. The current resolution context
 * is reused for nested resolutions so circular dependencies can be detected.
 *
 * @param string $id The service identifier.
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
 * @return string The service identifier.
 */
public function id(): string
{
    return $this->id;
}
```

## Error Handling

- Prefer specific exception types over generic exceptions.
- Exceptions should represent exceptional situations rather than normal control flow.
- Do not silently catch exceptions.
- When wrapping an exception, preserve the original exception as the previous exception.
- Exception messages must be clear and useful for debugging.
- Publicly relevant exceptions should be documented using `@throws`.

## Testing

- Add or update tests for observable behavior changed by an implementation.
- Prefer testing public behavior over implementation details.
- Keep tests deterministic.
- Tests must not depend on execution order.
- Use data providers when they improve readability.
- Prefer small real implementations or purpose-built test doubles over excessive mocking.
- Keep test names descriptive and behavior-focused.
- Update tests together with implementation changes.

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
- Update README files when installation, setup, public APIs, requirements, or usage change.
- Prefer explaining intent and behavior rather than merely restating implementation details.
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
