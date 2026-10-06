<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Http;

use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationMerger;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\Http\Message\Body\StringBody;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Decoding\RequestBodyDecoder;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Routing\Route;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use ExtendsSoftware\ExaPHP\Integration\Http\HttpModule;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use PHPUnit\Framework\TestCase;

final class RequestBodyDecodingIntegrationTest extends TestCase
{
    public function testConfiguredLimitAndDecoderFailuresFlowThroughDefaultExceptionMiddleware(): void
    {
        $decoder = null;
        $handler = $this->createStub(RequestHandler::class);
        $handler->method('handle')->willReturnCallback(static function (Request $request) use (&$decoder): Response {
            $decoder->decode($request);

            return new Response(StatusCode::Created);
        });
        $defaults = require new HttpModule()->configDirectory() . '/services.php';
        $configuration = new ConfigurationMerger()->merge(['http' => $defaults], ['application' => [
            'http' => [
                'request' => ['json' => ['maxBytes' => 4]],
                'routes' => ['create' => new Route('route.1', Method::Post, '/', 'handler')],
            ],
            'services' => ['handler' => new InstanceDefinition($handler)],
        ]]);
        $services = new ServiceLocatorFactory()->create($configuration);
        $decoder = $services->get(RequestBodyDecoder::class);
        $pipeline = $services->get(RequestHandler::class);
        foreach ([
            ['application/json', 'null', StatusCode::Created],
            ['application/json', '{', StatusCode::BadRequest],
            ['application/json', '[1,2]', StatusCode::ContentTooLarge],
            ['application/xml', '<a/>', StatusCode::UnsupportedMediaType],
        ] as [$type, $body, $status]) {
            $request = new Request(
                Method::Post, new Uri('/'), new Headers(['Content-Type' => $type]), new StringBody($body),
            );
            $this->assertSame($status, $pipeline->handle($request)->statusCode);
        }
    }
}
