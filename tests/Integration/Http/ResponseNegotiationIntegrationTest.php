<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Http;

use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationMerger;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Representation\ContentNegotiatingResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Representation\ResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use ExtendsSoftware\ExaPHP\Integration\Http\HttpModule;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use PHPUnit\Framework\TestCase;

final class ResponseNegotiationIntegrationTest extends TestCase
{
    public function testModuleDefaultsToJsonAndAcceptsAdditionalFactoryRegistrations(): void
    {
        $xml = $this->createMock(ResponseFactory::class);
        $xml->expects($this->once())->method('create')->with(['id' => 1])->willReturn(
            new Response(headers: new Headers(['Content-Type' => 'application/xml'])),
        );
        $defaults = require new HttpModule()->configDirectory() . '/services.php';
        $configuration = new ConfigurationMerger()->merge(['http' => $defaults], ['application' => [
            'http' => ['response' => ['factories' => ['application/xml' => 'xml']]],
            'services' => ['xml' => new InstanceDefinition($xml)],
        ]]);
        $factory = new ServiceLocatorFactory()->create($configuration)->get(ContentNegotiatingResponseFactory::class);
        $request = new Request(Method::Get, new Uri('/'));
        $this->assertSame(['application/json'], $factory->create($request, ['id' => 1])->headers->get('Content-Type'));
        $this->assertSame(['application/xml'], $factory->create(
            $request->withHeaders(new Headers(['Accept' => 'application/xml'])),
            ['id' => 1],
        )->headers->get('Content-Type'));
        $this->assertSame(StatusCode::NotAcceptable, $factory->create(
            $request->withHeaders(new Headers(['Accept' => 'text/html'])),
            [],
        )->statusCode);
    }

    public function testApplicationCanChangeTheDefaultRepresentation(): void
    {
        $xml = $this->createStub(ResponseFactory::class);
        $xml->method('create')->willReturn(new Response(headers: new Headers(['Content-Type' => 'application/xml'])));
        $defaults = require new HttpModule()->configDirectory() . '/services.php';
        $configuration = new ConfigurationMerger()->merge(['http' => $defaults], ['application' => [
            'http' => ['response' => ['default' => 'application/xml', 'factories' => ['application/xml' => 'xml']]],
            'services' => ['xml' => new InstanceDefinition($xml)],
        ]]);
        $factory = new ServiceLocatorFactory()->create($configuration)->get(ContentNegotiatingResponseFactory::class);
        $this->assertSame(['application/xml'], $factory->create(
            new Request(Method::Get, new Uri('/')),
            [],
        )->headers->get('Content-Type'));
    }
}
