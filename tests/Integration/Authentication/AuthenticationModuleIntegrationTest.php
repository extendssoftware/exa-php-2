<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Authentication;

use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationMerger;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\Authentication\Authenticator;
use ExtendsSoftware\ExaPHP\Authentication\Bearer\BearerTokenCredentials;
use ExtendsSoftware\ExaPHP\Authentication\Exception\AuthenticationFailedException;
use ExtendsSoftware\ExaPHP\Authentication\Exception\InvalidCredentialsException;
use ExtendsSoftware\ExaPHP\Authentication\Exception\UnsupportedCredentialsException;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\ProtocolVersion;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use ExtendsSoftware\ExaPHP\Http\Routing\Route;
use ExtendsSoftware\ExaPHP\Http\Routing\RouteGroup;
use ExtendsSoftware\ExaPHP\Http\Routing\RouteMatch;
use ExtendsSoftware\ExaPHP\Identity\Actor;
use ExtendsSoftware\ExaPHP\Integration\Authentication\AuthenticationModule;
use ExtendsSoftware\ExaPHP\Integration\Authentication\Http\AuthenticationMiddleware;
use ExtendsSoftware\ExaPHP\Integration\Authentication\Http\BearerAuthenticationResponseFactory;
use ExtendsSoftware\ExaPHP\Integration\Authentication\Http\BearerTokenCredentialsExtractor;
use ExtendsSoftware\ExaPHP\Integration\Http\HttpModule;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use TypeError;

use function implode;
use function iterator_to_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final class AuthenticationModuleIntegrationTest extends TestCase
{
    public function testAuthenticatesGroupedRouteAndReplacesExistingActorWithoutChangingOriginalRequest(): void
    {
        $actor = new Actor('verified', 'user');
        $untrusted = new Actor('untrusted', 'user');
        $authenticator = $this->createMock(Authenticator::class);
        $authenticator->expects($this->once())->method('authenticate')->willReturnCallback(
            static function (BearerTokenCredentials $credentials) use ($actor): Actor {
                self::assertSame('Secret+/._~-==', $credentials->token());

                return $actor;
            },
        );
        $handler = $this->createMock(RequestHandler::class);
        $handler->expects($this->once())->method('handle')->willReturnCallback(
            static function (Request $request) use ($actor): Response {
                self::assertSame($actor, $request->attributes->get(Actor::class));
                self::assertSame('42', $request->attributes->get(RouteMatch::class)->parameter('id'));

                return new Response(StatusCode::Accepted);
            },
        );
        $services = $this->services([
            Authenticator::class => new InstanceDefinition($authenticator),
            'handler' => new InstanceDefinition($handler),
        ]);
        $request = new Request(Method::Get, new Uri('/private/42'), new Headers([
            'aUtHoRiZaTiOn' => 'bEaReR   Secret+/._~-==',
        ]))->withAttribute($untrusted);
        self::assertSame(StatusCode::Accepted, $services->get(RequestHandler::class)->handle($request)->statusCode);
        self::assertSame($untrusted, $request->attributes->get(Actor::class));
    }

    /** @param array<string, string|list<string>> $headers */
    #[DataProvider('clientFailures')]
    public function testRejectsClientFailuresWithoutResolvingTheHandler(
        array $headers,
        bool $verify,
        StatusCode $status,
        string $challenge,
    ): void {
        $authenticator = $this->createMock(Authenticator::class);
        $authenticator->expects($verify ? $this->once() : $this->never())->method('authenticate')
            ->willThrowException(new InvalidCredentialsException('Private verification details.'));
        $services = $this->services([Authenticator::class => new InstanceDefinition($authenticator)]);
        $request = new Request(
            Method::Get, new Uri('/private/42?access_token=ignored'), new Headers($headers),
            protocolVersion: ProtocolVersion::Http2,
        )->withAttribute(new Actor('untrusted', 'user'));
        $response = $services->get(RequestHandler::class)->handle($request);
        self::assertSame($status, $response->statusCode);
        self::assertSame([$challenge], $response->headers->get('WWW-Authenticate'));
        self::assertSame(['no-store'], $response->headers->get('Cache-Control'));
        self::assertSame(['application/problem+json'], $response->headers->get('Content-Type'));
        self::assertSame(ProtocolVersion::Http2, $response->protocolVersion);
        self::assertSame([
            'type' => 'about:blank',
            'title' => $status === StatusCode::BadRequest ? 'Bad Request' : 'Unauthorized',
            'status' => $status->value,
        ], json_decode(implode('', iterator_to_array($response->body->chunks())), true, flags: JSON_THROW_ON_ERROR));
    }

    /** @return iterable<string, array{array<string, string|list<string>>, bool, StatusCode, string}> */
    public static function clientFailures(): iterable
    {
        yield 'missing' => [[], false, StatusCode::Unauthorized, 'Bearer realm="api"'];
        yield 'other scheme' => [
            ['Authorization' => 'Basic abc'], false, StatusCode::Unauthorized, 'Bearer realm="api"',
        ];
        foreach (['', 'Bearer', 'Bearer ', "Bearer\ttoken", 'Bearer token,other', 'Bearer =',
            'Bearer token extra', 'Bearer token=middle'] as $header) {
            yield 'malformed: ' . $header => [
                ['Authorization' => $header], false, StatusCode::BadRequest,
                'Bearer realm="api", error="invalid_request"',
            ];
        }
        yield 'repeated' => [
            ['Authorization' => ['Bearer first', 'Bearer second']], false,
            StatusCode::BadRequest, 'Bearer realm="api", error="invalid_request"',
        ];
        yield 'rejected' => [
            ['Authorization' => 'Bearer token'], true, StatusCode::Unauthorized,
            'Bearer realm="api", error="invalid_token"',
        ];
    }

    #[DataProvider('verificationFailures')]
    public function testVerificationFailuresPropagateUnchanged(Throwable $failure): void
    {
        $authenticator = $this->createStub(Authenticator::class);
        $authenticator->method('authenticate')->willThrowException($failure);
        $next = $this->createMock(RequestHandler::class);
        $next->expects($this->never())->method('handle');
        $middleware = new AuthenticationMiddleware(
            new BearerTokenCredentialsExtractor(), $authenticator, new BearerAuthenticationResponseFactory(),
        );
        try {
            $middleware->process(new Request(Method::Get, new Uri('/'), new Headers([
                'Authorization' => 'Bearer token',
            ])), $next);
            self::fail('Expected verification failure.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /** @return iterable<array{Throwable}> */
    public static function verificationFailures(): iterable
    {
        yield [new AuthenticationFailedException('Unavailable.')];
        yield [new UnsupportedCredentialsException('Wrong verifier.')];
        yield [new TypeError('Implementation error.')];
    }

    public function testDownstreamCredentialExceptionIsNotTreatedAsAuthenticationRejection(): void
    {
        $authenticator = $this->createStub(Authenticator::class);
        $authenticator->method('authenticate')->willReturn(new Actor('42', 'user'));
        $handler = $this->createStub(RequestHandler::class);
        $handler->method('handle')->willThrowException(new InvalidCredentialsException('Downstream failure.'));
        $services = $this->services([
            Authenticator::class => new InstanceDefinition($authenticator),
            'handler' => new InstanceDefinition($handler),
        ]);
        $response = $services->get(RequestHandler::class)->handle(new Request(
            Method::Get, new Uri('/private/42'), new Headers(['Authorization' => 'Bearer token']),
        ));
        self::assertSame(StatusCode::InternalServerError, $response->statusCode);
        self::assertSame([], $response->headers->get('WWW-Authenticate'));
    }

    public function testPublicRouteDoesNotRequireAnAuthenticatorService(): void
    {
        $handler = $this->createStub(RequestHandler::class);
        $handler->method('handle')->willReturn(new Response(StatusCode::Accepted));
        $services = $this->services(['handler' => new InstanceDefinition($handler)]);
        self::assertFalse($services->has(Authenticator::class));
        self::assertSame(StatusCode::Accepted, $services->get(RequestHandler::class)->handle(
            new Request(Method::Get, new Uri('/public')),
        )->statusCode);
    }

    /** @param array<string, object> $definitions */
    private function services(array $definitions): ServiceLocator
    {
        $http = require new HttpModule()->configDirectory() . '/services.php';
        $authentication = require new AuthenticationModule()->configDirectory() . '/services.php';
        $configuration = new ConfigurationMerger()->merge(['http' => $http, 'authentication' => $authentication], [
            'application' => [
                'services' => $definitions,
                'http' => ['routes' => [
                    'protected' => new RouteGroup('/private', [AuthenticationMiddleware::class], [
                        new Route('private', Method::Get, '/{id}', 'handler'),
                    ]),
                    'public' => new Route('public', Method::Get, '/public', 'handler'),
                ]],
            ],
        ]);

        return new ServiceLocatorFactory()->create($configuration);
    }
}
