<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator\Definition;

use Closure;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;

/**
 * Describes a service produced by a factory receiving the locator.
 */
final readonly class FactoryDefinition implements ServiceDefinition
{
    /**
     * The factory used to produce the service.
     *
     * @var Closure(ServiceLocator): object
     */
    public Closure $factory;

    /**
     * Creates a definition from a service factory.
     *
     * @param callable(ServiceLocator): object $factory The factory used to produce the service.
     */
    public function __construct(callable $factory)
    {
        $this->factory = $factory(...);
    }
}
