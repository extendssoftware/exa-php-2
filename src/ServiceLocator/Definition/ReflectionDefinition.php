<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator\Definition;

/**
 * Describes a service whose constructor dependencies are resolved from their declared class or interface types.
 */
final readonly class ReflectionDefinition implements ServiceDefinition
{
    /**
     * Creates a reflection-based service definition.
     *
     * @param class-string $className The service implementation class.
     */
    public function __construct(public string $className) {}
}
