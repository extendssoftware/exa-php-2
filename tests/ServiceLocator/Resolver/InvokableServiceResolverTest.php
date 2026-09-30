<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\ServiceLocator\Resolver;

use ArgumentCountError;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\AliasDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ServiceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceResolutionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\UnsupportedDefinitionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\InvokableServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;

final class InvokableServiceResolverTest extends TestCase
{
    public function testWrapsConstructorErrors(): void
    {
        try {
            new InvokableServiceResolver()->resolve(
                new InvokableDefinition(AliasDefinition::class),
                $this->createStub(ServiceLocator::class),
            );
            self::fail('Expected a construction failure.');
        } catch (ServiceResolutionException $exception) {
            self::assertInstanceOf(ArgumentCountError::class, $exception->getPrevious());
        }
    }

    public function testConstructsFreshInstancesWithoutConsultingTheLocator(): void
    {
        $resolver = new InvokableServiceResolver();
        $definition = new InvokableDefinition(stdClass::class);
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::never())->method('get');
        $locator->expects(self::never())->method('has');

        $first = $resolver->resolve($definition, $locator);
        $second = $resolver->resolve($definition, $locator);

        self::assertInstanceOf(stdClass::class, $first);
        self::assertInstanceOf(stdClass::class, $second);
        self::assertNotSame($first, $second);
    }

    public function testWrapsMissingClassFailuresWithTheirOriginalCause(): void
    {
        $definition = new InvokableDefinition('ExaPHP\\MissingService');

        try {
            new InvokableServiceResolver()->resolve($definition, $this->createStub(ServiceLocator::class));
            self::fail('Expected a service resolution failure.');
        } catch (ServiceResolutionException $exception) {
            self::assertStringContainsString($definition->className, $exception->getMessage());
            self::assertInstanceOf(Throwable::class, $exception->getPrevious());
        }
    }

    public function testRejectsNonInstantiableClasses(): void
    {
        $this->expectException(ServiceResolutionException::class);
        $this->expectExceptionMessageIs(sprintf('Could not construct service class "%s".', ServiceLocator::class));

        new InvokableServiceResolver()->resolve(
            new InvokableDefinition(ServiceLocator::class),
            $this->createStub(ServiceLocator::class),
        );
    }

    public function testSupportsOnlyItsDefinitionType(): void
    {
        $resolver = new InvokableServiceResolver();

        self::assertTrue($resolver->supports(new InvokableDefinition(stdClass::class)));
        self::assertFalse($resolver->supports($this->createStub(ServiceDefinition::class)));
    }

    public function testRejectsUnsupportedDefinitionsWithoutConsultingTheLocator(): void
    {
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::never())->method('get');
        $locator->expects(self::never())->method('has');

        $this->expectException(UnsupportedDefinitionException::class);

        new InvokableServiceResolver()->resolve($this->createStub(ServiceDefinition::class), $locator);
    }
}
