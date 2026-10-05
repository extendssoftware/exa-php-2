<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Application\Factory;

use ArrayObject;
use ExtendsSoftware\ExaPHP\Application\ApplicationException;
use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Application\Configuration\Exception\InvalidConfigurationException;
use ExtendsSoftware\ExaPHP\Application\Configuration\Exception\InvalidServiceDefinitionException;
use ExtendsSoftware\ExaPHP\Application\Configuration\Exception\ReservedServiceDefinitionException;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\AliasDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ReflectionDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ServiceLocatorFactoryTest extends TestCase
{
    public function testRegistersTheExactConfigurationWithoutRequiringServices(): void
    {
        $configuration = new Configuration(['blog' => ['timeout' => 10]]);
        $locator = new ServiceLocatorFactory()->create($configuration);

        self::assertSame($configuration, $locator->get(Configuration::class));
        self::assertFalse($configuration->has('services'));
    }

    public function testSupportsAllBuiltInDefinitionsAndInjectsTheConfiguration(): void
    {
        $instance = new stdClass();
        $prototype = new readonly class(new Configuration([])) {
            public function __construct(public Configuration $configuration) {}
        };
        $configuration = new Configuration([
            'timeout' => 10,
            'services' => [
                'instance' => new InstanceDefinition($instance),
                'alias' => new AliasDefinition('instance'),
                'factory' => new FactoryDefinition(
                    static fn(ServiceLocator $services): object =>
                        (object) ['timeout' => $services->get(Configuration::class)->get('timeout')],
                ),
                'invokable' => new InvokableDefinition(ArrayObject::class),
                'reflection' => new ReflectionDefinition($prototype::class),
            ],
        ]);
        $locator = new ServiceLocatorFactory()->create($configuration);

        self::assertSame($instance, $locator->get('instance'));
        self::assertSame($instance, $locator->get('alias'));
        self::assertSame(10, $locator->get('factory')->timeout);
        self::assertInstanceOf(ArrayObject::class, $locator->get('invokable'));
        self::assertSame($configuration, $locator->get('reflection')->configuration);
        self::assertArrayNotHasKey(Configuration::class, $configuration->get('services'));
    }

    public function testCreatesIndependentLocatorsAndDefersFactoryExecutionUntilLookup(): void
    {
        $calls = 0;
        $configuration = new Configuration(['services' => [
            'service' => new FactoryDefinition(static function (ServiceLocator $services) use (&$calls): object {
                ++$calls;

                return new stdClass();
            }),
        ]]);
        $factory = new ServiceLocatorFactory();
        $first = $factory->create($configuration);
        $second = $factory->create($configuration);

        self::assertSame(0, $calls);
        $service = $first->get('service');
        self::assertSame($service, $first->get('service'));
        self::assertNotSame($service, $second->get('service'));
        self::assertSame(2, $calls);
    }

    public function testAcceptsEmptyServiceMapsAndNumericIdentifiers(): void
    {
        $factory = new ServiceLocatorFactory();
        $configuration = new Configuration(['services' => []]);
        self::assertSame($configuration, $factory->create($configuration)->get(Configuration::class));

        $instance = new stdClass();
        $locator = $factory->create(new Configuration(['services' => [0 => new InstanceDefinition($instance)]]));
        self::assertSame($instance, $locator->get('0'));
    }

    #[DataProvider('invalidValues')]
    public function testRejectsNonArrayServiceSections(mixed $value): void
    {
        $this->expectException(InvalidConfigurationException::class);

        new ServiceLocatorFactory()->create(new Configuration(['services' => $value]));
    }

    #[DataProvider('invalidValues')]
    public function testRejectsInvalidServiceDefinitionsWithTheIdentifier(mixed $value): void
    {
        try {
            new ServiceLocatorFactory()->create(new Configuration(['services' => ['client' => $value]]));
            self::fail('Expected an invalid service definition exception.');
        } catch (InvalidServiceDefinitionException $exception) {
            self::assertInstanceOf(ApplicationException::class, $exception);
            self::assertStringContainsString('client', $exception->getMessage());
        }
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidValues(): iterable
    {
        yield 'null' => [null];
        yield 'false' => [false];
        yield 'number' => [10];
        yield 'class string' => [stdClass::class];
        yield 'bare object' => [new stdClass()];
        yield 'bare factory' => [static fn(): object => new stdClass()];
    }

    public function testRejectsArrayServiceDefinitions(): void
    {
        $this->expectException(InvalidServiceDefinitionException::class);

        new ServiceLocatorFactory()->create(new Configuration(['services' => ['client' => []]]));
    }

    public function testRejectsAttemptsToReplaceTheConfigurationServiceIncludingNull(): void
    {
        foreach ([null, new InstanceDefinition(new Configuration([]))] as $definition) {
            try {
                $configuration = new Configuration(['services' => [Configuration::class => $definition]]);
                new ServiceLocatorFactory()->create($configuration);
                self::fail('Expected a reserved service definition exception.');
            } catch (ReservedServiceDefinitionException $exception) {
                self::assertInstanceOf(ApplicationException::class, $exception);
                self::assertStringContainsString(Configuration::class, $exception->getMessage());
            }
        }
    }
}
