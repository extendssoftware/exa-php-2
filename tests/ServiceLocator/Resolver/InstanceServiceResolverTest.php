<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\ServiceLocator\Resolver;

use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ServiceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\UnsupportedDefinitionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\InstanceServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\TestCase;
use stdClass;

final class InstanceServiceResolverTest extends TestCase
{
    public function testReturnsTheExactInstanceWithoutConsultingTheLocator(): void
    {
        $service = new stdClass();
        $definition = new InstanceDefinition($service);
        $resolver = new InstanceServiceResolver();
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::never())->method('get');
        $locator->expects(self::never())->method('has');

        self::assertSame($service, $resolver->resolve($definition, $locator));
        self::assertSame($service, $resolver->resolve($definition, $locator));
    }

    public function testSupportsOnlyItsDefinitionType(): void
    {
        $resolver = new InstanceServiceResolver();

        self::assertTrue($resolver->supports(new InstanceDefinition(new stdClass())));
        self::assertFalse($resolver->supports($this->createStub(ServiceDefinition::class)));
    }

    public function testRejectsUnsupportedDefinitionsWithoutConsultingTheLocator(): void
    {
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::never())->method('get');
        $locator->expects(self::never())->method('has');

        $this->expectException(UnsupportedDefinitionException::class);

        new InstanceServiceResolver()->resolve($this->createStub(ServiceDefinition::class), $locator);
    }
}
