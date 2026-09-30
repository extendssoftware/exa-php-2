<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator\Definition;

/**
 * Describes a service provided by an existing object.
 */
final readonly class InstanceDefinition implements ServiceDefinition
{
    /**
     * Creates a definition retaining the supplied service instance.
     *
     * @param object $instance The existing service instance.
     */
    public function __construct(public object $instance)
    {
    }
}
