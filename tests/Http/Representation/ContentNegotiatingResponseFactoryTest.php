<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Representation;

use ExtendsSoftware\ExaPHP\Http\Representation\Exception\InvalidResponseFactoryException;
use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\ProtocolVersion;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Representation\ContentNegotiatingResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Representation\JsonResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Representation\ResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

final class ContentNegotiatingResponseFactoryTest extends TestCase
{
    #[DataProvider('preferences')]
    public function testNegotiatesOnlyAcceptableFactories(?string $accept, ?string $expected): void
    {
        $factories = [];
        foreach (['application/xml', 'application/json'] as $type) {
            $factory = $this->createMock(ResponseFactory::class);
            $factory->expects($expected === $type ? $this->once() : $this->never())->method('create')
                ->with(['id' => 1], StatusCode::Created)->willReturn(
                    new Response(StatusCode::Created, new Headers(['Content-Type' => $type])),
                );
            $factories[$type] = $factory;
        }
        $request = new Request(
            Method::Get,
            new Uri('/'),
            new Headers($accept === null ? [] : ['Accept' => $accept]),
            protocolVersion: ProtocolVersion::Http2,
        );
        $negotiator = new ContentNegotiatingResponseFactory($factories);
        $response = $negotiator->create($request, ['id' => 1], StatusCode::Created);
        $this->assertSame($expected === null ? StatusCode::NotAcceptable : StatusCode::Created, $response->statusCode);
        $this->assertSame($expected === null ? [] : [$expected], $response->headers->get('Content-Type'));
        $this->assertSame(['Accept'], $response->headers->get('Vary'));
        $this->assertSame(ProtocolVersion::Http2, $response->protocolVersion);
    }

    /** @return iterable<array{?string, ?string}> */
    public static function preferences(): iterable
    {
        yield [null, 'application/json'];
        yield ['*/*', 'application/json'];
        yield ['application/*', 'application/json'];
        yield ['application/xml', 'application/xml'];
        yield ['APPLICATION/JSON', 'application/json'];
        yield ['application/xml;q=0.9, application/json;q=0.5', 'application/xml'];
        yield ['application/json;q=0, */*;q=1', 'application/xml'];
        yield ['application/*;q=0, */*;q=1', null];
        yield ['application/json;q=0.2, application/*;q=0.9', 'application/xml'];
        yield ['application/xml;q=0.5, */*;q=0.5', 'application/xml'];
        yield ['application/xml,application/json', 'application/json'];
        yield ['application/json;q=0,application/xml;q=0', null];
        yield ['text/html', null];
        yield ['', null];
        yield ['application/json;q=1.1', null];
        yield ['application/json;q=0.1234', null];
        yield ['application/json;q=0.2;q=1', null];
        yield ['application/json;profile="a,b", application/xml', 'application/xml'];
        yield ['application/json;charset=utf-8', null];
        yield ['application/json;q=1.000', 'application/json'];
    }

    public function testPreservesVaryFieldsAndDoesNotDuplicateAccept(): void
    {
        $factory = new ContentNegotiatingResponseFactory(['application/json' => new JsonResponseFactory()]);
        $request = new Request(Method::Get, new Uri('/'), new Headers(['Accept' => ['text/html', 'application/json']]));
        foreach (['Origin', 'Origin, aCcEpT', '*'] as $vary) {
            $response = $factory->create($request, null, headers: new Headers(['Vary' => $vary]));
            $this->assertSame($vary === 'Origin' ? ['Origin', 'Accept'] : [$vary], $response->headers->get('Vary'));
        }
    }

    public function testSelectedFactoryFailurePropagatesWithoutFallback(): void
    {
        $failure = new RuntimeException('Failed encoder');
        $json = $this->createMock(ResponseFactory::class);
        $json->expects($this->once())->method('create')->willThrowException($failure);
        $other = $this->createMock(ResponseFactory::class);
        $other->expects($this->never())->method('create');
        $factory = new ContentNegotiatingResponseFactory(['application/json' => $json, 'text/plain' => $other]);
        try {
            $factory->create(new Request(Method::Get, new Uri('/')), []);
            $this->fail('Expected encoder failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    /** @param array<array-key, mixed> $registrations */
    #[DataProvider('invalidRegistrations')]
    public function testRejectsInvalidRegistrations(array $registrations): void
    {
        $this->expectException(InvalidResponseFactoryException::class);
        new ContentNegotiatingResponseFactory($registrations);
    }

    /** @return iterable<array{array<array-key, mixed>}> */
    public static function invalidRegistrations(): iterable
    {
        yield [[]];
        yield [['application/json' => new stdClass()]];
        yield [['*/*' => new JsonResponseFactory()]];
        yield [['application/json;profile=x' => new JsonResponseFactory()]];
        yield [['application/json' => new JsonResponseFactory(), 'Application/Json' => new JsonResponseFactory()]];
    }
}
