<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\ErrorHandling;

use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\MappingExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\Exception\InvalidExceptionProblemDetailsMapperException;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ExceptionProblemDetailsMapper;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetails;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\ProtocolVersion;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;

use function implode;
use function iterator_to_array;
use function json_decode;

final class MappingExceptionResponseFactoryTest extends TestCase
{
    public function testFirstMatchWinsWithOriginalExceptionAndRequest(): void
    {
        $exception = new TypeError('original');
        $request = new Request(Method::Get, new Uri('/'), protocolVersion: ProtocolVersion::Http2);
        $calls = [];
        $first = $this->createMock(ExceptionProblemDetailsMapper::class);
        $first->expects(self::once())->method('map')->with(self::identicalTo($exception), self::identicalTo($request))
            ->willReturnCallback(static function () use (&$calls): ?ProblemDetails {
                $calls[] = 'first';

                return null;
            });
        $second = $this->createMock(ExceptionProblemDetailsMapper::class);
        $second->expects(self::once())->method('map')->with(self::identicalTo($exception), self::identicalTo($request))
            ->willReturnCallback(static function () use (&$calls): ProblemDetails {
                $calls[] = 'second';

                return new ProblemDetails(StatusCode::Conflict, 'Conflict', extensions: ['id' => 42]);
            });
        $third = $this->createMock(ExceptionProblemDetailsMapper::class);
        $third->expects(self::never())->method('map');
        $fallback = $this->createMock(ExceptionResponseFactory::class);
        $fallback->expects(self::never())->method('create');
        $response = new MappingExceptionResponseFactory([$first, $second, $third], $fallback)->create($exception, $request);
        self::assertSame(['first', 'second'], $calls);
        self::assertSame(StatusCode::Conflict, $response->statusCode);
        self::assertSame(ProtocolVersion::Http2, $response->protocolVersion);
        self::assertSame(42, json_decode(implode('', iterator_to_array($response->body->chunks())), true)['id']);
    }

    public function testUnmatchedFailureReachesFallbackUnchanged(): void
    {
        $exception = new TypeError('original');
        $request = new Request(Method::Get, new Uri('/'));
        $response = new Response();
        $mapper = self::createStub(ExceptionProblemDetailsMapper::class);
        $mapper->method('map')->willReturn(null);
        $fallback = $this->createMock(ExceptionResponseFactory::class);
        $fallback->expects(self::once())->method('create')
            ->with(self::identicalTo($exception), self::identicalTo($request))->willReturn($response);
        self::assertSame($response, new MappingExceptionResponseFactory([$mapper], $fallback)->create($exception, $request));
    }

    public function testMapperFailurePropagatesWithoutContinuing(): void
    {
        $failure = new TypeError('mapping failed');
        $mapper = self::createStub(ExceptionProblemDetailsMapper::class);
        $mapper->method('map')->willThrowException($failure);
        $later = $this->createMock(ExceptionProblemDetailsMapper::class);
        $later->expects(self::never())->method('map');
        $fallback = $this->createMock(ExceptionResponseFactory::class);
        $fallback->expects(self::never())->method('create');
        try {
            new MappingExceptionResponseFactory([$mapper, $later], $fallback)->create(
                new TypeError('original'),
                new Request(Method::Get, new Uri('/')),
            );
            self::fail('Expected mapper failure.');
        } catch (TypeError $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /** @param array<array-key, mixed> $mappers */
    #[DataProvider('invalidMappers')]
    public function testRejectsInvalidMappers(array $mappers): void
    {
        $this->expectException(InvalidExceptionProblemDetailsMapperException::class);
        new MappingExceptionResponseFactory($mappers, self::createStub(ExceptionResponseFactory::class));
    }

    /** @return iterable<array{array<array-key, mixed>}> */
    public static function invalidMappers(): iterable
    {
        yield [[new stdClass()]];
        yield [['name' => new stdClass()]];
    }
}
