<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\ServiceLocator\Definition;

use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ReflectionDefinition;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ReflectionDefinitionTest extends TestCase
{
    public function testExposesTheServiceClassName(): void
    {
        $definition = new ReflectionDefinition(stdClass::class);

        self::assertSame(stdClass::class, $definition->className);
    }
}
