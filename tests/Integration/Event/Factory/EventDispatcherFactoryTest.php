<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Event\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationMerger;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\Event\Listener\EventListener;
use ExtendsSoftware\ExaPHP\Event\Exception\InvalidEventRegistrationException;
use ExtendsSoftware\ExaPHP\Integration\Event\Exception\InvalidEventConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\Event\Factory\EventDispatcherFactory;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\Tests\Event\Fixture\ParentEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class EventDispatcherFactoryTest extends TestCase
{
    public function testCreatesADispatcherUsingConfiguredServiceIdentifiers(): void
    {
        $message = new ParentEvent();
        $listener = $this->createMock(EventListener::class);
        $listener->expects(self::once())->method('handle')->with(self::identicalTo($message));
        $configuration = new Configuration([
            'events' => [ParentEvent::class => ['listener' => 'listener']],
            'services' => [
                'listener' => new InstanceDefinition($listener),
                'bus' => new FactoryDefinition(new EventDispatcherFactory()->create(...)),
            ],
        ]);
        $locator = new ServiceLocatorFactory()->create($configuration);

        $locator->get('bus')->dispatch($message);
    }

    public function testMergesModuleListenersAndPreservesOrderWhenApplicationReplacesARegistration(): void
    {
        $calls = [];
        $original = $this->createMock(EventListener::class);
        $original->expects(self::never())->method('handle');
        $replacement = $this->createMock(EventListener::class);
        $replacement->expects(self::once())->method('handle')
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'replacement';
            });
        $activity = $this->createMock(EventListener::class);
        $activity->expects(self::once())->method('handle')
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'activity';
            });
        $configuration = new ConfigurationMerger()->merge([
            'search' => [
                'events' => [ParentEvent::class => ['search' => 'search']],
                'services' => ['search' => new InstanceDefinition($original)],
            ],
            'activity' => [
                'events' => [ParentEvent::class => ['activity' => 'activity']],
                'services' => ['activity' => new InstanceDefinition($activity)],
            ],
        ], [
            'application' => [
                'events' => [ParentEvent::class => ['search' => 'replacement']],
                'services' => ['replacement' => new InstanceDefinition($replacement)],
            ],
        ]);
        $locator = new ServiceLocatorFactory()->create($configuration);
        $dispatcher = new EventDispatcherFactory()->create($locator);
        self::assertSame([], $calls);
        $dispatcher->dispatch(new ParentEvent());
        self::assertSame(['replacement', 'activity'], $calls);
    }

    #[DataProvider('emptyConfigurations')]
    public function testCreatesIndependentEmptyDispatcheres(array $values): void
    {
        $locator = new ServiceLocatorFactory()->create(new Configuration($values));
        $factory = new EventDispatcherFactory();

        self::assertNotSame($factory->create($locator), $factory->create($locator));
    }

    /**
     * @return iterable<array{array<array-key, mixed>}>
     */
    public static function emptyConfigurations(): iterable
    {
        yield [[]];
        yield [['events' => []]];
        yield [['events' => [ParentEvent::class => []]]];
    }

    #[DataProvider('invalidConfigurations')]
    public function testRejectsMalformedConfiguration(array $values): void
    {
        $locator = new ServiceLocatorFactory()->create(new Configuration($values));
        $this->expectException(InvalidEventConfigurationException::class);
        new EventDispatcherFactory()->create($locator);
    }

    /**
     * @return iterable<array{array<array-key, mixed>}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield [['events' => null]];
        yield [['events' => 'invalid']];
        yield [['events' => [[]]]];
        yield [['events' => ['' => []]]];
        yield [['events' => [ParentEvent::class => null]]];
        yield [['events' => [ParentEvent::class => ['listener']]]];
        yield [['events' => [ParentEvent::class => ['' => 'listener']]]];
        yield [['events' => [ParentEvent::class => ['listener' => '']]]];
        yield [['events' => [ParentEvent::class => ['listener' => new stdClass()]]]];
    }

    public function testRejectsAnIncorrectConfigurationService(): void
    {
        $locator = $this->createStub(ServiceLocator::class);
        $locator->method('get')->willReturn(new stdClass());
        $this->expectException(InvalidEventConfigurationException::class);
        new EventDispatcherFactory()->create($locator);
    }

    public function testPropagatesServiceResolutionFailuresUnchanged(): void
    {
        $failure = new ServiceNotFoundException('Missing listener');
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::exactly(2))->method('get')->willReturnCallback(
            static function (string $id) use ($failure): object {
                if ($id === Configuration::class) {
                    return new Configuration(['events' => [ParentEvent::class => ['listener' => 'missing']]]);
                }

                throw $failure;
            },
        );

        try {
            new EventDispatcherFactory()->create($locator);
            self::fail('Expected a service resolution failure.');
        } catch (ServiceNotFoundException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function testDispatcherRejectsAnInvalidResolvedListener(): void
    {
        $locator = new ServiceLocatorFactory()->create(new Configuration([
            'events' => [ParentEvent::class => ['listener' => 'listener']],
            'services' => ['listener' => new InstanceDefinition(new stdClass())],
        ]));
        $this->expectException(InvalidEventRegistrationException::class);
        new EventDispatcherFactory()->create($locator);
    }
}
