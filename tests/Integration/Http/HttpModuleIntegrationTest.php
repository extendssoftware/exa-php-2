<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Http;

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationMerger;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\Http\ExceptionHandling\ExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Method;
use ExtendsSoftware\ExaPHP\Http\Middleware\Middleware;
use ExtendsSoftware\ExaPHP\Http\Request;
use ExtendsSoftware\ExaPHP\Http\Response;
use ExtendsSoftware\ExaPHP\Http\Routing\Route;
use ExtendsSoftware\ExaPHP\Http\Server\ResponseEmitter;
use ExtendsSoftware\ExaPHP\Http\Server\ServerRequestFactory;
use ExtendsSoftware\ExaPHP\Http\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Uri;
use ExtendsSoftware\ExaPHP\Integration\Http\HttpModule;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function bin2hex;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;

final class HttpModuleIntegrationTest extends TestCase
{
    public function testBootstrapsDefaultServicesAndEmptyRouter(): void
    {
        $directory = sys_get_temp_dir() . '/exa-http-' . bin2hex(random_bytes(8));
        mkdir($directory);
        try {
            $application = new Application($directory);
            $application->registerModule(HttpModule::class);
            $services = $application->bootstrap();
            $handler = $services->get(RequestHandler::class);
            $this->assertSame($handler, $services->get(RequestHandler::class));
            $response = $handler->handle(new Request(Method::Get, new Uri('/')));
            $this->assertSame(StatusCode::NotFound, $response->statusCode);
            $this->assertInstanceOf(ServerRequestFactory::class, $services->get(ServerRequestFactory::class));
            $this->assertInstanceOf(ResponseEmitter::class, $services->get(ResponseEmitter::class));
            $application->shutdown();
        } finally {
            rmdir($directory);
        }
    }

    public function testMergesRoutesAndResolvesOnlySelectedHandlerAfterApplicationOverride(): void
    {
        $created = 0;
        $handler = $this->createStub(RequestHandler::class);
        $response = new Response(StatusCode::Accepted);
        $handler->method('handle')->willReturn($response);
        $defaults = require new HttpModule()->configDirectory() . '/services.php';
        $configuration = new ConfigurationMerger()->merge([
            'http' => $defaults,
            'articles' => ['http' => ['routes' => [
                'articles.show' => new Route(Method::Get, '/old', 'old'),
            ]]],
            'other' => ['http' => ['routes' => [
                'other.show' => new Route(Method::Get, '/other', 'unregistered'),
            ]]],
        ], ['app' => [
            'http' => ['routes' => ['articles.show' => new Route(Method::Get, '/articles', 'article')]],
            'services' => ['article' => new FactoryDefinition(static function () use (&$created, $handler) {
                ++$created;

                return $handler;
            })],
        ]]);
        $services = new ServiceLocatorFactory()->create($configuration);
        $pipeline = $services->get(RequestHandler::class);
        $this->assertSame(0, $created);
        $missing = $pipeline->handle(new Request(Method::Get, new Uri('/old')));
        $this->assertSame(StatusCode::NotFound, $missing->statusCode);
        $this->assertSame(
            StatusCode::MethodNotAllowed,
            $pipeline->handle(new Request(Method::Post, new Uri('/articles')))->statusCode,
        );
        $this->assertSame(0, $created);
        $this->assertSame($response, $pipeline->handle(new Request(Method::Get, new Uri('/articles'))));
        $this->assertSame(1, $created);
        $this->assertSame(
            StatusCode::InternalServerError,
            $pipeline->handle(new Request(Method::Get, new Uri('/other')))->statusCode,
        );
    }

    public function testNamedMiddlewareKeepsOrderAndUsesOverriddenExceptionFactory(): void
    {
        $calls = [];
        $first = $this->createMock(Middleware::class);
        $first->expects($this->once())->method('process')->willReturnCallback(
            static function (Request $request, RequestHandler $next) use (&$calls): Response {
                $calls[] = 'first';

                return $next->handle($request);
            },
        );
        $second = $this->createMock(Middleware::class);
        $second->expects($this->once())->method('process')->willReturnCallback(
            static function () use (&$calls): never {
                $calls[] = 'second';
                throw new RuntimeException('Failure');
            },
        );
        $factory = $this->createStub(ExceptionResponseFactory::class);
        $response = new Response(StatusCode::ServiceUnavailable);
        $factory->method('create')->willReturn($response);
        $defaults = require new HttpModule()->configDirectory() . '/services.php';
        $configuration = new ConfigurationMerger()->merge([
            'http' => $defaults,
            'module' => ['http' => ['middleware' => ['first' => 'old', 'second' => 'second']]],
        ], ['app' => [
            'http' => ['middleware' => ['first' => 'first']],
            'services' => [
                'first' => new InstanceDefinition($first),
                'second' => new InstanceDefinition($second),
                ExceptionResponseFactory::class => new InstanceDefinition($factory),
            ],
        ]]);
        $pipeline = new ServiceLocatorFactory()->create($configuration)->get(RequestHandler::class);
        $this->assertSame($response, $pipeline->handle(new Request(Method::Get, new Uri('/'))));
        $this->assertSame(['first', 'second'], $calls);
    }
}
