<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\ServiceLocator\Definition;

use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use PHPUnit\Framework\TestCase;
use stdClass;

final class InstanceDefinitionTest extends TestCase
{
    public function testExposesTheSuppliedServiceInstance(): void
    {
        $instance = new stdClass();
        $definition = new InstanceDefinition($instance);

        self::assertSame($instance, $definition->instance);
    }
}
