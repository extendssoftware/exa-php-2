<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\ServiceLocator\Definition;

use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\AliasDefinition;
use PHPUnit\Framework\TestCase;

final class AliasDefinitionTest extends TestCase
{
    public function testExposesTheTargetServiceIdentifier(): void
    {
        $definition = new AliasDefinition('service.target');

        self::assertSame('service.target', $definition->target);
    }
}
