# Changelog

All notable changes to ExaPHP will be documented in this file.

## [Unreleased]

### Added

- Application bootstrap with explicit module registration, optional module configuration, read-only configuration with
  dot-path lookup, application global and local overrides, service conflict detection, and service locator creation with
  configuration injection. Successful bootstrap is shared; distribution templates are excluded from loading.
- Service locator component with immutable definitions for instances, aliases, factories, and class construction;
  extensible resolvers; constructor injection; shared service instances; circular dependency detection; and a
  component-specific exception contract.
- Initial ExaPHP 2.0 project structure with Composer configuration requiring PHP ^8.5 and PSR-4 autoloading for the
  `ExtendsSoftware\ExaPHP` namespace.
- MIT license.
- Initial README with requirements, development setup, and testing instructions.
