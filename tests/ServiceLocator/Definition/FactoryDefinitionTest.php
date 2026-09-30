<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\ServiceLocator\Definition;

use Closure;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\TestCase;
use stdClass;

final class FactoryDefinitionTest extends TestCase
{
    public function testExposesTheSuppliedClosureWithoutInvokingIt(): void
    {
        $factory = static function (ServiceLocator $locator): object {
            self::fail('Creating a definition must not invoke the factory.');
        };

        $definition = new FactoryDefinition($factory);

        self::assertSame($factory, $definition->factory);
    }

    public function testConvertsCallableToClosureAndPreservesItsBehavior(): void
    {
        $service = new stdClass();
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::once())->method('get')->with('service')->willReturn($service);
        $factory = static fn (ServiceLocator $locator): object => $locator->get('service');

        $definition = new FactoryDefinition([$factory, '__invoke']);

        self::assertInstanceOf(Closure::class, $definition->factory);
        self::assertSame($service, ($definition->factory)($locator));
    }
}
