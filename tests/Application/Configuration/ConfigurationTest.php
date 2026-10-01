<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Application\Configuration;

use ExtendsSoftware\ExaPHP\Application\ApplicationException;
use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Application\Exception\ConfigurationNotFoundException;
use ExtendsSoftware\ExaPHP\Application\Exception\InvalidConfigurationPathException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ConfigurationTest extends TestCase
{
    public function testReturnsTopLevelValuesAndNestedSections(): void
    {
        $values = ['debug' => false, 'blog' => ['endpoint' => 'https://example.com', 'timeout' => 0]];
        $configuration = new Configuration($values);

        self::assertFalse($configuration->get('debug'));
        self::assertTrue($configuration->has('debug'));
        self::assertSame($values['blog'], $configuration->get('blog'));
        self::assertSame('https://example.com', $configuration->get('blog.endpoint'));
        self::assertSame(0, $configuration->get('blog.timeout'));
    }

    public function testNullAndEmptyArraysArePresentValues(): void
    {
        $configuration = new Configuration(['blog' => ['optional' => null, 'items' => []]]);

        self::assertTrue($configuration->has('blog.optional'));
        self::assertNull($configuration->get('blog.optional'));
        self::assertTrue($configuration->has('blog.items'));
        self::assertSame([], $configuration->get('blog.items'));
    }

    public function testResolvesNumericArrayKeys(): void
    {
        $configuration = new Configuration(['servers' => [['host' => 'localhost']]]);

        self::assertTrue($configuration->has('servers.0.host'));
        self::assertSame('localhost', $configuration->get('servers.0.host'));
    }

    public function testPreservesObjectIdentity(): void
    {
        $object = new stdClass();
        $configuration = new Configuration(['service' => $object]);

        self::assertSame($object, $configuration->get('service'));
    }

    public function testInputAndReturnedArrayChangesDoNotReplaceStoredValues(): void
    {
        $values = ['blog' => ['timeout' => 10]];
        $configuration = new Configuration($values);
        $values['blog']['timeout'] = 20;
        $section = $configuration->get('blog');
        $section['timeout'] = 30;

        self::assertSame(10, $configuration->get('blog.timeout'));
    }

    #[DataProvider('missingPaths')]
    public function testMissingPathsAreAbsentAndThrowOnLookup(string $path): void
    {
        $configuration = new Configuration([
            'blog' => ['timeout' => 10, 'optional' => null],
            'object' => (object) ['property' => 'value'],
            'literal.key' => 'value',
        ]);

        self::assertFalse($configuration->has($path));

        try {
            $configuration->get($path);
            self::fail('Expected a missing configuration exception.');
        } catch (ConfigurationNotFoundException $exception) {
            self::assertInstanceOf(ApplicationException::class, $exception);
            self::assertStringContainsString($path, $exception->getMessage());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function missingPaths(): iterable
    {
        yield 'unknown root' => ['missing'];
        yield 'unknown nested key' => ['blog.missing'];
        yield 'scalar parent' => ['blog.timeout.value'];
        yield 'null parent' => ['blog.optional.value'];
        yield 'object parent' => ['object.property'];
        yield 'literal dot key' => ['literal.key'];
    }

    #[DataProvider('invalidPaths')]
    public function testRejectsInvalidPathsForLookupAndExistenceChecks(string $path): void
    {
        $configuration = new Configuration([]);

        foreach (['get', 'has'] as $method) {
            try {
                $configuration->$method($path);
                self::fail('Expected an invalid configuration path exception.');
            } catch (InvalidConfigurationPathException $exception) {
                self::assertInstanceOf(ApplicationException::class, $exception);
                self::assertStringContainsString('non-empty segments', $exception->getMessage());
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidPaths(): iterable
    {
        yield 'empty path' => [''];
        yield 'leading separator' => ['.blog'];
        yield 'trailing separator' => ['blog.'];
        yield 'empty middle segment' => ['blog..timeout'];
    }
}
