<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\JsonRpc;

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Integration\JsonRpc\Exception\InvalidJsonRpcConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\JsonRpc\JsonRpcModule;
use ExtendsSoftware\ExaPHP\JsonRpc\DefaultJsonRpcServer;
use ExtendsSoftware\ExaPHP\JsonRpc\JsonRpcServer;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\MessageDecoder;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\MessageEncoder;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\RegisteredMethodDispatcher;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceResolutionException;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function glob;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

final class JsonRpcModuleIntegrationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/exa-json-rpc-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*.php') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testRegistersSharedServicesWithNoOtherModulesOrMethodConfiguration(): void
    {
        $application = new Application($this->directory);
        $application->registerModule(JsonRpcModule::class);
        $services = $application->bootstrap();

        try {
            $server = $services->get(JsonRpcServer::class);
            self::assertInstanceOf(DefaultJsonRpcServer::class, $server);
            foreach ([JsonRpcServer::class, MessageDecoder::class, MessageEncoder::class,
                RegisteredMethodDispatcher::class] as $id) {
                self::assertSame($services->get($id), $services->get($id));
            }
            self::assertSame(
                '{"jsonrpc":"2.0","error":{"code":-32601,"message":"Method not found"},"id":1}',
                $server->handle('{"jsonrpc":"2.0","method":"unknown","id":1}'),
            );
        } finally {
            $application->shutdown();
        }
    }

    public function testLoadsConfiguredHandlerServicesAndMergesMethodRegistrations(): void
    {
        file_put_contents($this->directory . '/a.global.php', <<<'CONFIG'
            <?php

            declare(strict_types=1);

            use ExtendsSoftware\ExaPHP\JsonRpc\Method\CallableMethodHandler;
            use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;

            return [
                'jsonRpc' => ['methods' => ['echo' => 'echo.handler']],
                'services' => [
                    'echo.handler' => new InstanceDefinition(
                        new CallableMethodHandler(static fn(array|stdClass $params): mixed => $params),
                    ),
                ],
            ];
            CONFIG);
        file_put_contents($this->directory . '/b.global.php', <<<'CONFIG'
            <?php

            declare(strict_types=1);

            return ['jsonRpc' => ['methods' => ['alias' => 'echo.handler']]];
            CONFIG);
        $application = new Application($this->directory);
        $application->registerModule(JsonRpcModule::class);
        $services = $application->bootstrap();

        try {
            $server = $services->get(JsonRpcServer::class);
            self::assertSame(
                '[{"jsonrpc":"2.0","result":[1],"id":1},{"jsonrpc":"2.0","result":[2],"id":2}]',
                $server->handle('[
                    {"jsonrpc":"2.0","method":"echo","params":[1],"id":1},
                    {"jsonrpc":"2.0","method":"alias","params":[2],"id":2},
                    {"jsonrpc":"2.0","method":"alias"}
                ]'),
            );
        } finally {
            $application->shutdown();
        }
    }

    public function testApplicationConfigurationCanReplaceTheServer(): void
    {
        file_put_contents($this->directory . '/override.global.php', <<<'CONFIG'
            <?php

            declare(strict_types=1);

            use ExtendsSoftware\ExaPHP\JsonRpc\JsonRpcServer;
            use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;

            return ['services' => [
                JsonRpcServer::class => new FactoryDefinition(
                    static fn(): JsonRpcServer => new class implements JsonRpcServer {
                        public function handle(string $message): ?string
                        {
                            return '{"custom":true}';
                        }
                    },
                ),
            ]];
            CONFIG);
        $application = new Application($this->directory);
        $application->registerModule(JsonRpcModule::class);
        $services = $application->bootstrap();

        try {
            self::assertSame('{"custom":true}', $services->get(JsonRpcServer::class)->handle('{}'));
        } finally {
            $application->shutdown();
        }
    }

    public function testInvalidConfigurationIsReportedWhenTheServerIsResolved(): void
    {
        file_put_contents($this->directory . '/invalid.global.php', <<<'CONFIG'
            <?php

            declare(strict_types=1);

            return ['jsonRpc' => ['methods' => null]];
            CONFIG);
        $application = new Application($this->directory);
        $application->registerModule(JsonRpcModule::class);
        $services = $application->bootstrap();

        try {
            $services->get(JsonRpcServer::class);
            self::fail('Expected invalid configuration to prevent server resolution.');
        } catch (ServiceResolutionException $exception) {
            self::assertInstanceOf(InvalidJsonRpcConfigurationException::class, $exception->getPrevious());
        } finally {
            $application->shutdown();
        }
    }

    public function testMissingHandlerFailuresRetainTheirServiceLocatorException(): void
    {
        file_put_contents($this->directory . '/missing.global.php', <<<'CONFIG'
            <?php

            declare(strict_types=1);

            return ['jsonRpc' => ['methods' => ['example' => 'missing.handler']]];
            CONFIG);
        $application = new Application($this->directory);
        $application->registerModule(JsonRpcModule::class);
        $services = $application->bootstrap();

        try {
            $this->expectException(ServiceNotFoundException::class);
            $services->get(JsonRpcServer::class);
        } finally {
            $application->shutdown();
        }
    }
}
