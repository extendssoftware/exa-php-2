<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Authorization;

use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationMerger;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\Authorization\AuthorizationGuard;
use ExtendsSoftware\ExaPHP\Authorization\AuthorizationRequest;
use ExtendsSoftware\ExaPHP\Authorization\Authorizer;
use ExtendsSoftware\ExaPHP\Authorization\Exception\AccessDeniedException;
use ExtendsSoftware\ExaPHP\Authorization\Exception\InvalidAuthorizationRequestException;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\MalformedRequestBodyException;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ExceptionProblemDetailsMapper;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetails;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\ProtocolVersion;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use ExtendsSoftware\ExaPHP\Http\Middleware\ExceptionHandlingMiddleware;
use ExtendsSoftware\ExaPHP\Identity\Actor;
use ExtendsSoftware\ExaPHP\Integration\Authorization\AuthorizationModule;
use ExtendsSoftware\ExaPHP\Integration\Authorization\Http\AccessDeniedProblemDetailsMapper;
use ExtendsSoftware\ExaPHP\Integration\Http\HttpModule;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use TypeError;

use function implode;
use function iterator_to_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final class AuthorizationModuleIntegrationTest extends TestCase
{
    public function testGuardResolvesWithApplicationAuthorizerWithoutHttpModule(): void
    {
        $request = new AuthorizationRequest(new Actor('42', 'user'), 'article.publish');
        $authorizer = $this->createMock(Authorizer::class);
        $authorizer->expects($this->once())->method('isGranted')->with($this->identicalTo($request))->willReturn(false);
        $defaults = require new AuthorizationModule()->configDirectory() . '/services.php';
        $configuration = new ConfigurationMerger()->merge(['authorization' => $defaults], ['application' => [
            'services' => [Authorizer::class => new InstanceDefinition($authorizer)],
        ]]);
        $services = new ServiceLocatorFactory()->create($configuration);
        $guard = $services->get(AuthorizationGuard::class);
        self::assertSame($guard, $services->get(AuthorizationGuard::class));
        $this->expectException(AccessDeniedException::class);

        $guard->assertGranted($request);
    }

    public function testHttpBoundaryMapsDenialWithoutResolvingAnAuthorizerOrExposingRequestData(): void
    {
        $services = $this->services();
        self::assertFalse($services->has(Authorizer::class));
        $denied = new AuthorizationRequest(
            new Actor('private-user', 'private-kind'),
            'private-action',
            (object) ['secret' => 'private-resource'],
        );
        $handler = $this->createStub(RequestHandler::class);
        $handler->method('handle')->willThrowException(new AccessDeniedException($denied));
        $request = new Request(Method::Get, new Uri('/private-path'), protocolVersion: ProtocolVersion::Http2);
        $response = $services->get(ExceptionHandlingMiddleware::class)->process($request, $handler);

        self::assertSame(StatusCode::Forbidden, $response->statusCode);
        self::assertSame(ProtocolVersion::Http2, $response->protocolVersion);
        self::assertSame(['application/problem+json'], $response->headers->get('Content-Type'));
        self::assertSame(['no-store'], $response->headers->get('Cache-Control'));
        self::assertSame(
            ['type' => 'about:blank', 'title' => 'Forbidden', 'status' => 403],
            json_decode(implode('', iterator_to_array($response->body->chunks())), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    #[DataProvider('unrelatedFailures')]
    public function testDeclinesUnrelatedFailuresAndRetainsHttpFallbacks(Throwable $failure, StatusCode $status): void
    {
        $request = new Request(Method::Get, new Uri('/'));
        self::assertNull(new AccessDeniedProblemDetailsMapper()->map($failure, $request));
        $factory = $this->services()->get(ExceptionResponseFactory::class);

        self::assertSame($status, $factory->create($failure, $request)->statusCode);
    }

    /** @return iterable<string, array{Throwable, StatusCode}> */
    public static function unrelatedFailures(): iterable
    {
        yield 'invalid authorization request' => [
            new InvalidAuthorizationRequestException('Private details.'), StatusCode::InternalServerError,
        ];
        yield 'evaluation failure' => [new RuntimeException('Private details.'), StatusCode::InternalServerError];
        yield 'engine error' => [new TypeError('Private details.'), StatusCode::InternalServerError];
        yield 'decoding failure' => [new MalformedRequestBodyException(), StatusCode::BadRequest];
        yield 'wrapped denial' => [
            new RuntimeException('Wrapper', previous: new AccessDeniedException(
                new AuthorizationRequest(new Actor('42', 'user'), 'article.publish'),
            )),
            StatusCode::InternalServerError,
        ];
    }

    public function testApplicationCanOverrideTheNamedDenialMapper(): void
    {
        $mapper = $this->createStub(ExceptionProblemDetailsMapper::class);
        $mapper->method('map')->willReturn(new ProblemDetails(StatusCode::NotFound, 'Not Found'));
        $services = $this->services([
            'http' => ['exceptionMappers' => ['authorization.accessDenied' => 'custom-denial']],
            'services' => ['custom-denial' => new InstanceDefinition($mapper)],
        ]);
        $denial = new AccessDeniedException(new AuthorizationRequest(new Actor('42', 'user'), 'article.view'));
        $response = $services->get(ExceptionResponseFactory::class)->create(
            $denial, new Request(Method::Get, new Uri('/')),
        );

        self::assertSame(StatusCode::NotFound, $response->statusCode);
    }

    /** @param array<string, mixed> $overrides */
    private function services(array $overrides = []): ServiceLocator
    {
        $http = require new HttpModule()->configDirectory() . '/services.php';
        $authorization = require new AuthorizationModule()->configDirectory() . '/services.php';
        $configuration = new ConfigurationMerger()->merge(
            ['http' => $http, 'authorization' => $authorization], ['application' => $overrides],
        );

        return new ServiceLocatorFactory()->create($configuration);
    }
}
