<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Application;

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Application\Exception\ApplicationStateException;
use ExtendsSoftware\ExaPHP\Application\Exception\DuplicateModuleException;
use ExtendsSoftware\ExaPHP\Application\Exception\InvalidModuleException;
use ExtendsSoftware\ExaPHP\Application\Module\Module;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

use function strtolower;

final class ApplicationTest extends TestCase
{
    public function testConfigurationIsUnavailableBeforeBootstrap(): void
    {
        $this->expectException(ApplicationStateException::class);

        new Application('/unused')->config();
    }

    #[DataProvider('invalidModules')]
    public function testRejectsInvalidModuleClasses(string $class): void
    {
        $this->expectException(InvalidModuleException::class);

        new Application('/unused')->registerModule($class);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidModules(): iterable
    {
        yield 'missing class' => ['MissingApplicationModule'];
        yield 'unrelated class' => [stdClass::class];
        yield 'interface' => [Module::class];
        $module = new class('required') implements Module {
            public function __construct(string $value) {}
        };
        yield 'required argument' => [$module::class];
    }

    public function testRejectsDuplicateModuleNamesRegardlessOfCase(): void
    {
        $module = new class implements Module {};
        $application = new Application('/unused');
        $application->registerModule($module::class);
        $this->expectException(DuplicateModuleException::class);

        $application->registerModule(strtolower($module::class));
    }
}
