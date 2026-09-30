<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator\Definition;

/**
 * Describes a service constructed without arguments.
 */
final readonly class InvokableDefinition implements ServiceDefinition
{
    /**
     * Creates a service definition.
     *
     * @param class-string $className The service implementation class.
     */
    public function __construct(public string $className) {}
}
