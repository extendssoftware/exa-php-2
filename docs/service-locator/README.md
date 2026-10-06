# Service locator

The service locator provides object services by string identifier. `ServiceLocator` exposes `get(string $id): object`
and `has(string $id): bool`. `DefinitionServiceLocator` implements this contract with explicit definitions, ordered
resolvers, shared instances, and circular dependency detection.

Use the locator in application setup and factories to assemble dependencies. Application services can receive those
dependencies through their constructors.

## Basic usage

After installing the repository's Composer dependencies, load `vendor/autoload.php`. This example uses only PHP classes
and the service locator component:

```php
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\AliasDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\DefinitionServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\AliasServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\FactoryServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\InstanceServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;

require 'vendor/autoload.php';

$locator = new DefinitionServiceLocator(
    definitions: [
        DateTimeZone::class => new InstanceDefinition(new DateTimeZone('Europe/Amsterdam')),
        DateTimeImmutable::class => new FactoryDefinition(
            static fn(ServiceLocator $services): DateTimeImmutable =>
                new DateTimeImmutable('2026-01-01 12:00:00', $services->get(DateTimeZone::class)),
        ),
        'report.date' => new AliasDefinition(DateTimeImmutable::class),
    ],
    resolvers: [
        new InstanceServiceResolver(),
        new FactoryServiceResolver(),
        new AliasServiceResolver(),
    ],
);

$date = $locator->get('report.date');
assert($locator->has(DateTimeImmutable::class));
assert($date === $locator->get(DateTimeImmutable::class));
```

## Configure registrations and resolvers

Pass an identifier-to-`ServiceDefinition` array and an ordered list of `ServiceResolver` objects to the locator.
Both collections remain fixed after construction. Create another locator when you need different registrations.

Identifiers are lookup keys, independent of the class described by a definition. You may use application names such as
`report.date`, class names, or interface names. Register constructor dependencies under their declared type names when
using reflection-based injection.

`get()` has a generic PHPDoc return type: class and interface identifiers infer their corresponding object type, while
custom string identifiers return `object`. For example, `$locator->get(DateTimeZone::class)` infers `DateTimeZone`.
Register compatible objects under class and interface identifiers; this inference describes the registration convention
and does not add runtime type validation.

Include a resolver for every definition type you use; the locator does not install default resolvers. The first resolver
whose `supports()` method returns `true` handles the definition. A failure in `supports()` or `resolve()` ends the
request;
later resolvers are not tried as fallbacks.

`has()` checks registration only. It does not invoke resolvers, load the service class, inspect constructor parameters,
or validate alias targets. A `true` result therefore does not guarantee that `get()` will succeed.

## Choose a definition

All built-in definitions are immutable and live in `ExtendsSoftware\ExaPHP\ServiceLocator\Definition`.
Their matching resolvers live in `ExtendsSoftware\ExaPHP\ServiceLocator\Resolver`.

| Definition | Constructor argument | Matching resolver |
| --- | --- | --- |
| `InstanceDefinition` | `object $instance` | `InstanceServiceResolver` |
| `AliasDefinition` | `string $target` | `AliasServiceResolver` |
| `FactoryDefinition` | `callable $factory` | `FactoryServiceResolver` |
| `InvokableDefinition` | `string $className` | `InvokableServiceResolver` |
| `ReflectionDefinition` | `string $className` | `ReflectionServiceResolver` |

### Existing objects

Use `InstanceDefinition` for an object you have already constructed. Resolution returns that exact object without
cloning it. An immutable definition does not make the supplied object immutable.

### Aliases

Use `AliasDefinition` to expose a registered service under another identifier, for example an interface name or an
application-specific name. The resolver calls `get()` on the target, so aliases and their targets return the same shared
object. Alias chains are supported. Missing targets fail at lookup time, and alias cycles raise
`CircularDependencyException`.

### Factories

Use `FactoryDefinition` when construction requires configuration values, explicit argument choices, or custom setup.
Its callable receives the current `ServiceLocator` and must return an object. It is stored as a `Closure` and is invoked
when the service is first requested, rather than during registration.

Resolve related services through the supplied locator, as shown in the basic example. This preserves shared instances
and circular dependency detection. A non-object result causes `ServiceResolutionException`.

### Construction without arguments

Use `InvokableDefinition` for a class that can be constructed with `new $className()`. Despite the name, an `__invoke()`
method is not required and is not called. Constructor defaults can supply optional arguments; required arguments cause
construction to fail. The class must be loadable and constructible.

### Constructor injection

Use `ReflectionDefinition` to construct a class using its constructor's declared dependency types. Each required named
class or interface type is resolved through the locator using that type's name as the identifier. Register every needed
dependency explicitly; unregistered classes are not constructed automatically.

For example, suppose your application provides an autoloadable `App\Report\ReportGenerator` whose constructor accepts
an `App\Report\Formatter`, and an `App\Report\JsonFormatter` implementing that interface with no required arguments:

```php
<?php

declare(strict_types=1);

use App\Report\Formatter;
use App\Report\JsonFormatter;
use App\Report\ReportGenerator;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\AliasDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ReflectionDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\DefinitionServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\AliasServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\InvokableServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\ReflectionServiceResolver;

require 'vendor/autoload.php';

$locator = new DefinitionServiceLocator(
    [
        JsonFormatter::class => new InvokableDefinition(JsonFormatter::class),
        Formatter::class => new AliasDefinition(JsonFormatter::class),
        ReportGenerator::class => new ReflectionDefinition(ReportGenerator::class),
    ],
    [new InvokableServiceResolver(), new AliasServiceResolver(), new ReflectionServiceResolver()],
);

$reportGenerator = $locator->get(ReportGenerator::class);
```

The following rules determine whether reflection can supply a constructor argument:

- A single named class or interface type is resolved by identifier. `self` and `parent` refer to the class declaring the
  constructor parameter and its parent, respectively, including for inherited constructors.
- A registered named dependency takes precedence over a declared default. If its resolution fails, the failure
  propagates;
  the default is not used as a fallback.
- An unregistered named dependency uses its declared default when available. Without a default, lookup raises
  `ServiceNotFoundException`, even if the parameter is nullable.
- Scalar, untyped, union, and intersection parameters use their declared defaults when available. Required parameters of
  these kinds raise `UnresolvableParameterException`.
- Variadic parameters receive no arguments, including variadic parameters passed by reference.
- Other parameters passed by reference raise `UnresolvableParameterException`, even when they have defaults.

A class with no constructor is supported. A class that cannot be instantiated, such as an interface or abstract class,
raises `ServiceResolutionException`. Use a factory for constructors that reflection cannot satisfy or when you need to
override defaults explicitly.

## Shared instances and resolution lifetime

`DefinitionServiceLocator` caches each successful resolution by identifier for its lifetime. Repeated `get()` calls
return
the same object. Registering the same factory or class definition under two different identifiers resolves each
identifier
separately; use aliases when they should share one service. Instance definitions still return their supplied object.

Failed resolutions are not cached and can be retried. Dependencies successfully resolved before a later failure remain
cached. Resolvers themselves do not provide caching: direct calls to factory or construction resolvers execute again.

Circular dependency detection covers nested lookups through the same locator, including aliases, factories, and
reflection-based injection. A cycle reports the active path, such as `a -> b -> a`. The path is cleaned up after success
or failure, so a failed request does not leave an identifier marked as resolving.

Resolution tracking is synchronous and local to each locator. Independent locators do not share a resolution path;
cycles spanning independent locators are not tracked as one dependency chain.

## Handle failures

Catch `ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException` to handle component failures as a group, or catch
one of the specific exceptions in `ExtendsSoftware\ExaPHP\ServiceLocator\Exception`:

| Exception | Meaning and remedy |
| --- | --- |
| `ServiceNotFoundException` | An identifier is missing; register the service, dependency, or alias target. |
| `UnsupportedDefinitionException` | No resolver supports the definition; configure a matching resolver. |
| `UnresolvableParameterException` | Reflection cannot supply a constructor argument; use a factory. |
| `CircularDependencyException` | A dependency repeats an active identifier; break the reported cycle. |
| `ServiceResolutionException` | Loading, inspection, construction, or factory execution failed; inspect the cause. |

Calling a built-in resolver directly with the wrong definition also raises `UnsupportedDefinitionException`.

Factory and reflection resolvers propagate existing component exceptions unchanged. Other exceptions and engine errors
from their factory, loading, reflection, or construction work are wrapped in `ServiceResolutionException`, preserving
the
original failure in `getPrevious()`. The invokable resolver wraps all loading and construction failures, including
component exceptions. Alias target failures propagate unchanged.

The locator itself does not translate exceptions or errors thrown by custom resolvers. Such failures propagate unchanged
after resolution-path cleanup, so a custom resolver must translate its own failures into the component exception
contract.

## Extend service resolution

For another way to describe a service, implement the marker interface
[`ServiceDefinition`](../../src/ServiceLocator/Definition/ServiceDefinition.php) and pair it with a
[`ServiceResolver`](../../src/ServiceLocator/Resolver/ServiceResolver.php). Keep configuration in the definition and
resolution
behavior in the resolver. Prefer an immutable definition and inject any collaborators into the resolver's constructor.

Implement these methods:

- `supports(ServiceDefinition $definition): bool` checks support without constructing the service.
- `resolve(ServiceDefinition $definition, ServiceLocator $serviceLocator): object` produces the object and uses the
  supplied locator for dependencies or alias targets. Report unsupported definitions and resolution failures through
  `ServiceLocatorException` implementations.

The built-in [alias definition](../../src/ServiceLocator/Definition/AliasDefinition.php) and
[alias resolver](../../src/ServiceLocator/Resolver/AliasServiceResolver.php) provide a complete, small example of this
pair.
Register your definition under a service identifier and include your resolver in the locator's resolver list. Place it
before another resolver if both support the same definition and yours should take precedence. Instance sharing remains
the locator's responsibility.

For a different locator implementation, implement `ServiceLocator` directly. Its contract provides object lookup and
availability checks; the fixed registrations and caching behavior described here belong to `DefinitionServiceLocator`.
The public [`ResolutionContext`](../../src/ServiceLocator/ResolutionContext.php) tracks synchronous nested resolutions:
give each independent locator its own context and call `resolve($id, $callback)` around resolution. The callback must
return an object. The context detects repeated active identifiers, returns the callback result, and cleans up after all
outcomes without caching results or translating callback failures.
