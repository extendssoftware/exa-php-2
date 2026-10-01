<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Application\Configuration;

use ExtendsSoftware\ExaPHP\Application\ApplicationException;
use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationMerger;
use ExtendsSoftware\ExaPHP\Application\Exception\DuplicateServiceDefinitionException;
use ExtendsSoftware\ExaPHP\Application\Exception\InvalidConfigurationException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ConfigurationMergerTest extends TestCase
{
    public function testCombinesModuleSourcesWithoutTreatingRepeatedServicesAsModuleConflicts(): void
    {
        $original = new InstanceDefinition(new stdClass());
        $replacement = new InstanceDefinition(new stdClass());
        $values = new ConfigurationMerger()->mergeSources([
            'a.php' => ['settings' => ['retained' => true], 'services' => ['client' => $original]],
            'b.php' => ['settings' => ['added' => true], 'services' => ['client' => $replacement]],
        ]);

        self::assertSame(['retained' => true, 'added' => true], $values['settings']);
        self::assertSame(['client' => $replacement], $values['services']);
        self::assertSame([], new ConfigurationMerger()->mergeSources([]));
    }

    public function testMergesNestedDefaultsAndOverridesInSuppliedOrder(): void
    {
        $configuration = new ConfigurationMerger()->merge(
            [
                'Blog' => ['blog' => ['endpoint' => 'default', 'http' => ['timeout' => 10, 'retries' => 2]]],
                'Reporting' => ['blog' => ['http' => ['timeout' => 20]]],
            ],
            [
                'z.global.php' => ['blog' => ['endpoint' => 'global', 'http' => ['timeout' => 30]]],
                'a.local.php' => ['blog' => ['endpoint' => 'local']],
            ],
        );

        self::assertSame('local', $configuration->get('blog.endpoint'));
        self::assertSame(['timeout' => 30, 'retries' => 2], $configuration->get('blog.http'));
    }

    #[DataProvider('replacementValues')]
    public function testReplacesListsAndNonAssociativeValues(mixed $original, mixed $override): void
    {
        $configuration = new ConfigurationMerger()->merge(
            ['Module' => ['value' => $original]],
            ['application' => ['value' => $override]],
        );

        self::assertTrue($configuration->has('value'));
        self::assertSame($override, $configuration->get('value'));
    }

    /** @return iterable<string, array{mixed, mixed}> */
    public static function replacementValues(): iterable
    {
        yield 'lists replace without appending' => [['a', 'b'], ['c']];
        yield 'empty array clears a list' => [['a'], []];
        yield 'empty array clears a map' => [['key' => 'value'], []];
        yield 'list replaces map' => [['key' => 'value'], ['new']];
        yield 'map replaces list' => [['old'], ['key' => 'value']];
        yield 'scalar replaces map' => [['key' => 'value'], false];
        yield 'map replaces scalar' => [false, ['key' => 'value']];
        yield 'null replaces value' => ['old', null];
        yield 'zero replaces value' => [10, 0];
        yield 'object replaces object' => [(object) ['old' => true], (object) ['new' => true]];
    }

    public function testMergesSparseNumericKeysWithoutReindexing(): void
    {
        $configuration = new ConfigurationMerger()->merge(
            ['Module' => ['codes' => [10 => 'old', 20 => 'keep']]],
            ['application' => ['codes' => [10 => 'new', 30 => 'add']]],
        );

        self::assertSame([10 => 'new', 20 => 'keep', 30 => 'add'], $configuration->get('codes'));
    }

    public function testAddsServicesAndReplacesDefinitionsWithoutInvokingFactories(): void
    {
        $calls = 0;
        $original = new FactoryDefinition(static function (ServiceLocator $services) use (&$calls): object {
            ++$calls;

            return new stdClass();
        });
        $shared = new InstanceDefinition(new stdClass());
        $global = new InstanceDefinition(new stdClass());
        $local = new InstanceDefinition(new stdClass());
        $configuration = new ConfigurationMerger()->merge(
            ['Blog' => ['services' => ['client' => $original]], 'Shared' => ['services' => ['shared' => $shared]]],
            [
                'global' => ['services' => ['client' => $global, 'extra' => $original]],
                'local' => ['services' => ['client' => $local]],
            ],
        );

        self::assertSame(
            ['client' => $local, 'shared' => $shared, 'extra' => $original],
            $configuration->get('services'),
        );
        self::assertSame(0, $calls);
    }

    public function testEmptyServiceMapPreservesServicesAndNumericIdentifiersAreNotReindexed(): void
    {
        $original = new InstanceDefinition(new stdClass());
        $replacement = new InstanceDefinition(new stdClass());
        $configuration = new ConfigurationMerger()->merge(
            ['Module' => ['services' => [0 => $original, 1 => $original]]],
            ['global' => ['services' => [0 => $replacement]], 'local' => ['services' => []]],
        );

        self::assertSame([0 => $replacement, 1 => $original], $configuration->get('services'));
    }

    public function testServiceEntriesAreReplacedWholeWithoutValidatingTheirValues(): void
    {
        $configuration = new ConfigurationMerger()->merge(
            ['Module' => ['services' => ['client' => ['old' => true, 'shared' => true]]]],
            ['application' => ['services' => ['client' => ['new' => true]]]],
        );

        self::assertSame(['client' => ['new' => true]], $configuration->get('services'));
    }

    public function testRejectsModuleServiceConflictsEvenWithAnApplicationOverride(): void
    {
        $definition = new InstanceDefinition(new stdClass());

        try {
            new ConfigurationMerger()->merge(
                [
                    'Blog' => ['services' => ['client' => $definition]],
                    'Shop' => ['services' => ['client' => $definition]],
                ],
                ['application' => ['services' => ['client' => $definition]]],
            );
            self::fail('Expected a duplicate service definition exception.');
        } catch (DuplicateServiceDefinitionException $exception) {
            self::assertInstanceOf(ApplicationException::class, $exception);
            self::assertStringContainsString('client', $exception->getMessage());
            self::assertStringContainsString('Blog', $exception->getMessage());
            self::assertStringContainsString('Shop', $exception->getMessage());
        }
    }

    #[DataProvider('invalidServiceSections')]
    public function testRejectsInvalidServiceSectionsWithSourceContext(mixed $services): void
    {
        foreach (['module', 'application'] as $kind) {
            try {
                new ConfigurationMerger()->merge(
                    $kind === 'module' ? ['Blog' => ['services' => $services]] : [],
                    $kind === 'application' ? ['local.php' => ['services' => $services]] : [],
                );
                self::fail('Expected an invalid configuration exception.');
            } catch (InvalidConfigurationException $exception) {
                self::assertInstanceOf(ApplicationException::class, $exception);
                self::assertStringContainsString($kind === 'module' ? 'Blog' : 'local.php', $exception->getMessage());
            }
        }
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidServiceSections(): iterable
    {
        yield 'null' => [null];
        yield 'boolean' => [false];
        yield 'string' => ['invalid'];
        yield 'object' => [new stdClass()];
    }

    public function testDoesNotChangeInputsOrRetainStateBetweenMerges(): void
    {
        $modules = ['Blog' => ['blog' => ['timeout' => 10], 'services' => ['client' => new stdClass()]]];
        $application = ['local' => ['blog' => ['timeout' => 20]]];
        $merger = new ConfigurationMerger();

        $first = $merger->merge($modules, $application);
        $second = $merger->merge($modules);

        self::assertSame(20, $first->get('blog.timeout'));
        self::assertSame(10, $second->get('blog.timeout'));
        self::assertSame(10, $modules['Blog']['blog']['timeout']);
        self::assertSame(['local' => ['blog' => ['timeout' => 20]]], $application);
        self::assertFalse($merger->merge([])->has('services'));
    }
}
