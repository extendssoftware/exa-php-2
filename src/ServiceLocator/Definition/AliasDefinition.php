<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator\Definition;

/**
 * Describes a service resolved through another identifier.
 */
final readonly class AliasDefinition implements ServiceDefinition
{
    /**
     * Creates a service definition.
     *
     * @param string $target The target service identifier.
     */
    public function __construct(public string $target) {}
}
