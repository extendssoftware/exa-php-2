<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Cli;

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Application\Configuration\Exception\ConfigurationLoadException;
use ExtendsSoftware\ExaPHP\Application\Exception\ApplicationStateException;
use ExtendsSoftware\ExaPHP\Application\Module\Exception\ModuleShutdownException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\MissingArgumentException;
use ExtendsSoftware\ExaPHP\Cli\Routing\Exception\CommandNotFoundException;
use ExtendsSoftware\ExaPHP\Integration\Cli\CliModule;
use ExtendsSoftware\ExaPHP\Integration\Cli\CliRunner;
use ExtendsSoftware\ExaPHP\Integration\Cli\Exception\CliRunException;
use ExtendsSoftware\ExaPHP\Integration\Cli\Exception\InvalidCliArgumentsException;
use ExtendsSoftware\ExaPHP\Integration\Cli\Exception\InvalidCliConfigurationException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\Tests\Integration\Cli\Fixture\RunnerHandler;
use ExtendsSoftware\ExaPHP\Tests\Integration\Cli\Fixture\RunnerModule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use TypeError;

use function bin2hex;
use function fclose;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function rewind;
use function rmdir;
use function stream_get_contents;
use function sys_get_temp_dir;
use function unlink;
use function var_export;

final class CliRunnerIntegrationTest extends TestCase
{
    #[DataProvider('scenarios')]
    public function testDispatchesAndShutsDown(string $scenario, bool $shutdownFails): void
    {
        $directory = sys_get_temp_dir() . '/exa-cli-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $config = <<<'PHP'
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Cli\Definition\ArgumentDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\CommandDefinition;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use ExtendsSoftware\ExaPHP\Cli\Output\StreamOutput;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\Tests\Integration\Cli\Fixture\RunnerHandler;

PHP;
        $config .= '$handler = new RunnerHandler(' . var_export($scenario === 'failure', true) . ', '
            . var_export($shutdownFails, true) . ");\n";
        $config .= <<<'PHP'
$streams = new stdClass();
$streams->output = fopen('php://memory', 'w+');
return [
    'cli' => ['commands' => [
        'greet' => new CommandDefinition('greet', RunnerHandler::class, arguments: [new ArgumentDefinition('name')]),
    ]],
    'services' => [
        RunnerHandler::class => new InstanceDefinition($handler),
        'streams' => new InstanceDefinition($streams),
        Output::class => new InstanceDefinition(new StreamOutput($streams->output, $streams->output)),
    ],
];
PHP;
        file_put_contents($directory . '/cli.global.php', $config);
        $stream = null;
        try {
            $application = new Application($directory);
            $application->registerModule(CliModule::class);
            $application->registerModule(RunnerModule::class);
            $services = $application->bootstrap();
            $stream = $services->get('streams')->output;
            $argv = match ($scenario) {
                'unknown' => ['console.php', 'missing'],
                'parse' => ['console.php', 'greet'],
                default => ['console.php', 'greet', 'World'],
            };
            $failure = null;
            try {
                $code = new CliRunner()->run($application, $argv);
                $this->assertSame(17, $code);
            } catch (Throwable $exception) {
                $failure = $exception;
            }
            $handler = $services->get(RunnerHandler::class);
            $this->assertSame(
                $scenario === 'unknown' || $scenario === 'parse' ? ['shutdown'] : ['handle', 'shutdown'],
                $handler->calls,
            );
            $expected = match ($scenario) {
                'unknown' => CommandNotFoundException::class,
                'parse' => MissingArgumentException::class,
                'failure' => TypeError::class,
                default => null,
            };
            if ($shutdownFails && $expected !== null) {
                $this->assertInstanceOf(CliRunException::class, $failure);
                $this->assertInstanceOf($expected, $failure->getPrevious());
                $this->assertInstanceOf(ModuleShutdownException::class, $failure->shutdownFailure);
            } elseif ($shutdownFails) {
                $this->assertInstanceOf(ModuleShutdownException::class, $failure);
            } elseif ($expected !== null) {
                $this->assertInstanceOf($expected, $failure);
            } else {
                $this->assertNull($failure);
                rewind($stream);
                $this->assertSame('World', stream_get_contents($stream));
            }
            $this->expectException(ApplicationStateException::class);
            $application->bootstrap();
        } finally {
            fclose($stream);
            unlink($directory . '/cli.global.php');
            rmdir($directory);
        }
    }

    /** @return iterable<array{string, bool}> */
    public static function scenarios(): iterable
    {
        yield ['success', false];
        yield ['failure', false];
        yield ['unknown', false];
        yield ['parse', false];
        yield ['success', true];
        yield ['failure', true];
    }

    /** @param array<array-key, mixed> $argv */
    #[DataProvider('invalidArguments')]
    public function testRejectsInvalidArgumentsBeforeBootstrap(array $argv): void
    {
        $this->expectException(InvalidCliArgumentsException::class);
        new CliRunner()->run(new Application('/does/not/exist'), $argv);
    }

    /** @return iterable<array{array<array-key, mixed>}> */
    public static function invalidArguments(): iterable
    {
        yield [[]];
        yield [['console.php', '']];
        yield [['', 'greet']];
        yield [['console.php', 'greet', 1]];
        yield [[1 => 'console.php', 2 => 'greet']];
    }
    public function testBootstrapFailurePropagatesWithoutShutdownReplacingIt(): void
    {
        $directory = sys_get_temp_dir() . '/exa-missing-' . bin2hex(random_bytes(8));
        $this->expectException(ConfigurationLoadException::class);
        new CliRunner()->run(new Application($directory), ['console.php', 'greet']);
    }

    public function testRejectsIncompatibleServicesAndStopsApplication(): void
    {
        $directory = sys_get_temp_dir() . '/exa-runner-' . bin2hex(random_bytes(8));
        mkdir($directory);
        file_put_contents($directory . '/runner.global.php', <<<'PHP'
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Cli\Routing\CommandDispatcher;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;

$invalid = new InstanceDefinition(new stdClass());
return ['services' => [
    CommandDispatcher::class => $invalid,
    Output::class => $invalid,
]];
PHP);
        try {
            $application = new Application($directory);
            try {
                new CliRunner()->run($application, ['console.php', 'greet']);
                $this->fail('Expected invalid CLI service failure.');
            } catch (InvalidCliConfigurationException $exception) {
                $this->assertStringContainsString('Output', $exception->getMessage());
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
            new CliRunner()->run($application, ['console.php', 'greet']);
        } finally {
            rmdir($directory);
        }
    }
}
