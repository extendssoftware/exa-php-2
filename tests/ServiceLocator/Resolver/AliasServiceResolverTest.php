<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\ServiceLocator\Resolver;

use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\AliasDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ServiceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\UnsupportedDefinitionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\AliasServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\TestCase;
use stdClass;

final class AliasServiceResolverTest extends TestCase
{
    public function testReturnsTheTargetServiceFromTheSuppliedLocator(): void
    {
        $service = new stdClass();
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::once())->method('get')->with('target')->willReturn($service);
        $locator->expects(self::never())->method('has');

        self::assertSame($service, new AliasServiceResolver()->resolve(new AliasDefinition('target'), $locator));
    }

    public function testPropagatesTargetFailuresUnchanged(): void
    {
        $failure = new ServiceNotFoundException('Unknown target.');
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::once())->method('get')->with('target')->willThrowException($failure);

        $this->expectExceptionObject($failure);

        try {
            new AliasServiceResolver()->resolve(new AliasDefinition('target'), $locator);
        } catch (ServiceNotFoundException $exception) {
            self::assertSame($failure, $exception);
            throw $exception;
        }
    }

    public function testSupportsOnlyItsDefinitionType(): void
    {
        $resolver = new AliasServiceResolver();

        self::assertTrue($resolver->supports(new AliasDefinition('target')));
        self::assertFalse($resolver->supports($this->createStub(ServiceDefinition::class)));
    }

    public function testRejectsUnsupportedDefinitionsWithoutConsultingTheLocator(): void
    {
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::never())->method('get');
        $locator->expects(self::never())->method('has');

        $this->expectException(UnsupportedDefinitionException::class);

        new AliasServiceResolver()->resolve($this->createStub(ServiceDefinition::class), $locator);
    }
}
