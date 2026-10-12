<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Cli;

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Application\Exception\ApplicationStateException;
use ExtendsSoftware\ExaPHP\Application\Module\Exception\ModuleShutdownException;
use ExtendsSoftware\ExaPHP\Cli\ErrorHandling\DefaultExceptionPresenter;
use ExtendsSoftware\ExaPHP\Cli\ErrorHandling\ExceptionPresenter;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\MissingArgumentException;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use ExtendsSoftware\ExaPHP\Cli\Routing\Exception\CommandNotFoundException;
use ExtendsSoftware\ExaPHP\Integration\Cli\CliModule;
use ExtendsSoftware\ExaPHP\Integration\Cli\Exception\CliRunException;
use ExtendsSoftware\ExaPHP\Integration\Cli\ExceptionHandlingCliRunner;
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

final class ExceptionHandlingCliRunnerIntegrationTest extends TestCase
{
    #[DataProvider('scenarios')]
    public function testPresentsFailuresAfterShutdown(string $scenario, bool $shutdownFails): void
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
            $output = $services->get(Output::class);
            $presenter = $this->createMock(ExceptionPresenter::class);
            if ($scenario === 'success' && !$shutdownFails) {
                $presenter->expects(self::never())->method('present');
            } else {
                $presenter->expects(self::once())->method('present')->willReturnCallback(
                    static function (Throwable $exception, Output $output) use (&$failure): int {
                        $failure = $exception;

                        return new DefaultExceptionPresenter()->present($exception, $output);
                    },
                );
            }
            $runner = new ExceptionHandlingCliRunner(presenter: $presenter);
            $code = $runner->run($application, $argv, $output);
            $expectedCode = $shutdownFails || $scenario === 'failure' ? 1
                : ($scenario === 'success' ? 17 : 2);
            self::assertSame($expectedCode, $code);
            $handler = $services->get(RunnerHandler::class);
            self::assertSame(
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
                self::assertInstanceOf(CliRunException::class, $failure);
                self::assertInstanceOf($expected, $failure->getPrevious());
                self::assertInstanceOf(ModuleShutdownException::class, $failure->shutdownFailure);
            } elseif ($shutdownFails) {
                self::assertInstanceOf(ModuleShutdownException::class, $failure);
            } elseif ($expected !== null) {
                self::assertInstanceOf($expected, $failure);
            } else {
                self::assertNull($failure);
                rewind($stream);
                self::assertSame('World', stream_get_contents($stream));
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


    public function testPresentsBootstrapFailuresWithoutApplicationServices(): void
    {
        $output = $this->createMock(Output::class);
        $output->expects(self::once())->method('writeError')->with("Error: Command execution failed.\n");
        self::assertSame(1, new ExceptionHandlingCliRunner()->run(
            new Application('/does/not/exist'),
            ['console.php', 'greet'],
            $output,
        ));
    }

    public function testPresentsMalformedProcessArgumentsAsInvalidUsage(): void
    {
        $output = $this->createMock(Output::class);
        $output->expects(self::once())->method('writeError')->with("Error: CLI arguments must include the script path.\n");
        self::assertSame(2, new ExceptionHandlingCliRunner()->run(new Application('/does/not/exist'), [], $output));
    }

    public function testPresentationFailurePropagatesWithoutAnotherAttempt(): void
    {
        $failure = new TypeError('presenter failed');
        $output = self::createStub(Output::class);
        $presenter = $this->createMock(ExceptionPresenter::class);
        $presenter->expects(self::once())->method('present')->willThrowException($failure);
        try {
            new ExceptionHandlingCliRunner(presenter: $presenter)->run(new Application('/does/not/exist'), [], $output);
            self::fail('Expected presenter failure.');
        } catch (TypeError $exception) {
            self::assertSame($failure, $exception);
        }
    }
}
