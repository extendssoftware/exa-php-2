<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Http;

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Application\Exception\ApplicationStateException;
use ExtendsSoftware\ExaPHP\Application\Exception\ConfigurationLoadException;
use ExtendsSoftware\ExaPHP\Application\Exception\ModuleShutdownException;
use ExtendsSoftware\ExaPHP\Integration\Http\Exception\HttpRunException;
use ExtendsSoftware\ExaPHP\Integration\Http\Exception\InvalidHttpConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\Http\HttpRunner;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\Tests\Integration\Http\Fixture\RunnerModule;
use ExtendsSoftware\ExaPHP\Tests\Integration\Http\Fixture\RunnerServices;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use TypeError;

use function bin2hex;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;
use function var_export;

final class HttpRunnerIntegrationTest extends TestCase
{
    /** @param list<string> $expected */
    #[DataProvider('stages')]
    public function testExecutesOneRequestAndAlwaysShutsDown(string $stage, bool $cleanupFails, array $expected): void
    {
        $directory = sys_get_temp_dir() . '/exa-runner-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $config = <<<'PHP'
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Server\ResponseEmitter;
use ExtendsSoftware\ExaPHP\Http\Server\ServerRequestFactory;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\Tests\Integration\Http\Fixture\RunnerServices;

PHP;
        $config .= '$probe = new RunnerServices(' . var_export($stage, true) . ');' . "\n";
        $config .= <<<'PHP'
$services = [
    RunnerServices::class => new InstanceDefinition($probe),
    ServerRequestFactory::class => new InstanceDefinition($probe),
    RequestHandler::class => new InstanceDefinition($probe),
    ResponseEmitter::class => new InstanceDefinition($probe),
];
PHP;
        if ($cleanupFails) {
            $config .= "\n\$services['cleanup'] = new InstanceDefinition(new RunnerServices('shutdown'));";
        }
        $config .= "\nreturn ['services' => \$services];\n";
        file_put_contents($directory . '/runner.global.php', $config);
        try {
            $application = new Application($directory);
            $application->registerModule(RunnerModule::class);
            $caught = null;
            try {
                new HttpRunner()->run($application);
            } catch (Throwable $exception) {
                $caught = $exception;
            }
            $probe = $application->config()->get('services')[RunnerServices::class]->instance;
            $this->assertSame($expected, $probe->calls);
            if ($stage !== '' && $cleanupFails) {
                $this->assertInstanceOf(HttpRunException::class, $caught);
                $this->assertInstanceOf(TypeError::class, $caught->getPrevious());
                $this->assertSame($stage, $caught->getPrevious()->getMessage());
                $this->assertInstanceOf(ModuleShutdownException::class, $caught->shutdownFailure);
            } elseif ($cleanupFails) {
                $this->assertInstanceOf(ModuleShutdownException::class, $caught);
            } elseif ($stage !== '') {
                $this->assertInstanceOf(TypeError::class, $caught);
                $this->assertSame($stage, $caught->getMessage());
            } else {
                $this->assertNull($caught);
            }
            $application->shutdown();
            $this->assertSame($expected, $probe->calls);
            $this->expectException(ApplicationStateException::class);
            $application->bootstrap();
        } finally {
            unlink($directory . '/runner.global.php');
            rmdir($directory);
        }
    }

    /** @return iterable<array{string, bool, list<string>}> */
    public static function stages(): iterable
    {
        yield ['', false, ['create', 'handle', 'emit:HEAD', 'shutdown']];
        yield ['create', false, ['create', 'shutdown']];
        yield ['handle', false, ['create', 'handle', 'shutdown']];
        yield ['emit:HEAD', false, ['create', 'handle', 'emit:HEAD', 'shutdown']];
        yield ['', true, ['create', 'handle', 'emit:HEAD', 'shutdown']];
        yield ['handle', true, ['create', 'handle', 'shutdown']];
    }

    public function testBootstrapFailurePropagatesWithoutShutdownReplacingIt(): void
    {
        $directory = sys_get_temp_dir() . '/exa-missing-' . bin2hex(random_bytes(8));
        $this->expectException(ConfigurationLoadException::class);
        new HttpRunner()->run(new Application($directory));
    }

    public function testRejectsIncompatibleServicesAndStopsApplication(): void
    {
        $directory = sys_get_temp_dir() . '/exa-runner-' . bin2hex(random_bytes(8));
        mkdir($directory);
        file_put_contents($directory . '/runner.global.php', <<<'PHP'
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Server\ResponseEmitter;
use ExtendsSoftware\ExaPHP\Http\Server\ServerRequestFactory;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;

$invalid = new InstanceDefinition(new stdClass());
return ['services' => [
    ServerRequestFactory::class => $invalid,
    RequestHandler::class => $invalid,
    ResponseEmitter::class => $invalid,
]];
PHP);
        try {
            $application = new Application($directory);
            try {
                new HttpRunner()->run($application);
                $this->fail('Expected invalid HTTP service failure.');
            } catch (InvalidHttpConfigurationException $exception) {
                $this->assertStringContainsString('registered contracts', $exception->getMessage());
            }
            $this->expectException(ApplicationStateException::class);
            $application->bootstrap();
        } finally {
            unlink($directory . '/runner.global.php');
            rmdir($directory);
        }
    }

    public function testMissingServicesAreNotAutomaticallyRegistered(): void
    {
        $directory = sys_get_temp_dir() . '/exa-runner-' . bin2hex(random_bytes(8));
        mkdir($directory);
        try {
            $application = new Application($directory);
            $this->expectException(ServiceNotFoundException::class);
            new HttpRunner()->run($application);
        } finally {
            rmdir($directory);
        }
    }
}
