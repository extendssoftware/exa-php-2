# Changelog

All notable changes to ExaPHP will be documented in this file.

## [Unreleased]

### Added

- DDD component with aggregate and domain event contracts, optional abstract aggregate roots, a reusable recorded-event
  collection, composable generic specifications, and an article example.
- Event component with event and listener contracts, synchronous dispatch to ordered listener lists, validated
  registrations, and component exceptions.
- PHP 8.5 CLI development container and just recipes for dependency installation and full or targeted test runs.
- GitHub Actions workflow running the full test suite through the shared just recipes on pushes and pull requests.
- Integration component with CQRS and Event modules providing default bus and dispatcher services, configuration-driven
  factories, command handler and middleware configuration under `cqrs.command`, mergeable keyed listener registrations,
  and a root exception contract.
- CQRS component with command and generic query contracts, synchronous command and query buses with validated handler
  registrations, command middleware with extensible dispatch context, and component exceptions. Custom command bus
  implementations must accept the optional context argument; existing dispatch calls remain valid.
- Application bootstrap with explicit module registration, optional module configuration, read-only configuration with
  dot-path lookup, application global and local overrides, service conflict detection, and service locator creation with
  configuration injection. Optional module bootstrap and shutdown hooks support ordered startup, reverse-order cleanup,
  and preservation of hook failures. Successful bootstrap is shared while running; distribution templates are excluded.
- Service locator component with immutable definitions for instances, aliases, factories, and class construction;
  extensible resolvers; constructor injection; shared service instances; circular dependency detection; and a
  component-specific exception contract.
- Initial ExaPHP 2.0 project structure with Composer configuration requiring PHP ^8.5 and PSR-4 autoloading for the
  `ExtendsSoftware\ExaPHP` namespace.
- MIT license.
- Initial README with requirements, development setup, and testing instructions.
