<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\ServiceLocator\Definition;

use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;
use PHPUnit\Framework\TestCase;
use stdClass;

final class InvokableDefinitionTest extends TestCase
{
    public function testExposesTheServiceClassName(): void
    {
        $definition = new InvokableDefinition(stdClass::class);

        self::assertSame(stdClass::class, $definition->className);
    }
}
