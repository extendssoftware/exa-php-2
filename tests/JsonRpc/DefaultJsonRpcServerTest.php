<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\JsonRpc;

use Error;
use ExtendsSoftware\ExaPHP\JsonRpc\DefaultJsonRpcServer;
use ExtendsSoftware\ExaPHP\JsonRpc\ErrorCode;
use ExtendsSoftware\ExaPHP\JsonRpc\JsonRpcException;
use ExtendsSoftware\ExaPHP\JsonRpc\JsonRpcServer;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\MessageDecoder;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\MessageEncoder;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\CallableMethodHandler;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\Exception\InvalidMethodRegistrationException;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\Exception\MethodCallException;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\RegisteredMethodDispatcher;
use JsonSerializable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Throwable;
use TypeError;

use function json_decode;
use function json_encode;

use const INF;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

final class DefaultJsonRpcServerTest extends TestCase
{
    private JsonRpcServer $server;

    protected function setUp(): void
    {
        $this->server = $this->createServer([
            'echo' => new CallableMethodHandler(static fn(array|stdClass $params): array|stdClass => $params),
        ]);
    }

    public function testDispatchesPositionalParametersWithoutProtocolMetadata(): void
    {
        self::assertSame(
            '{"jsonrpc":"2.0","result":[42,23],"id":1}',
            $this->server->handle('{"jsonrpc":"2.0","method":"echo","params":[42,23],"id":1}'),
        );
    }

    public function testPreservesNamedParametersAndNestedObjects(): void
    {
        $response = $this->decode($this->server->handle(
            '{"jsonrpc":"2.0","method":"echo","params":{"nested":{},"list":[],"_meta":"ordinary data"},"id":1}',
        ));

        self::assertEquals(
            (object) ['nested' => new stdClass(), 'list' => [], '_meta' => 'ordinary data'],
            $response->result,
        );
    }

    public function testSuppliesEmptyPositionalParametersWhenOmitted(): void
    {
        self::assertSame(
            '{"jsonrpc":"2.0","result":[],"id":1}',
            $this->server->handle('{"jsonrpc":"2.0","method":"echo","id":1}'),
        );
    }

    #[DataProvider('identifiers')]
    public function testPreservesIdentifiersIncludingExplicitNull(float|int|string|null $id): void
    {
        $request = json_encode(['jsonrpc' => '2.0', 'method' => 'echo', 'id' => $id], JSON_PRESERVE_ZERO_FRACTION);
        self::assertIsString($request);

        $response = $this->decode($this->server->handle($request));

        self::assertObjectHasProperty('id', $response);
        self::assertSame($id, $response->id);
        self::assertObjectHasProperty('result', $response);
    }

    /** @return iterable<array{float|int|string|null}> */
    public static function identifiers(): iterable
    {
        yield [null];
        yield [0];
        yield [-1];
        yield [0.0];
        yield [1.25];
        yield [''];
        yield ['123'];
        yield ['request-é'];
    }

    #[DataProvider('malformedJson')]
    public function testReturnsParseErrorWithNullIdentifier(string $message): void
    {
        $this->assertError($this->decode($this->server->handle($message)), -32700, 'Parse error');
    }

    /** @return iterable<array{string}> */
    public static function malformedJson(): iterable
    {
        yield [''];
        yield ['{'];
        yield ['[{"jsonrpc":"2.0","method":"echo"},'];
        yield ['{"jsonrpc":"2.0","method":"echo","params":["bad' . "\xff" . '"]}'];
        yield ['{"jsonrpc":"2.0",}'];
    }

    #[DataProvider('invalidRequests')]
    public function testReturnsInvalidRequestWithNullIdentifier(string $message): void
    {
        $this->assertError($this->decode($this->server->handle($message)), -32600, 'Invalid Request');
    }

    /** @return iterable<array{string}> */
    public static function invalidRequests(): iterable
    {
        yield ['null'];
        yield ['true'];
        yield ['42'];
        yield ['"request"'];
        yield ['{}'];
        yield ['{"jsonrpc":"1.0","method":"echo","id":1}'];
        yield ['{"jsonrpc":2,"method":"echo","id":1}'];
        yield ['{"method":"echo","id":1}'];
        yield ['{"jsonrpc":"2.0","method":false,"id":1}'];
        yield ['{"jsonrpc":"2.0","id":1,"result":42}'];
        foreach (['true', '{}', '[]', '1e400'] as $id) {
            yield ['{"jsonrpc":"2.0","method":"echo","id":' . $id . '}'];
        }
        foreach (['null', 'true', '42', '"params"'] as $params) {
            yield ['{"jsonrpc":"2.0","method":"echo","params":' . $params . ',"id":1}'];
        }
    }

    public function testReturnsMethodNotFoundForUnregisteredMethod(): void
    {
        $response = $this->decode($this->server->handle('{"jsonrpc":"2.0","method":"unknown","id":"request"}'));

        $this->assertError($response, -32601, 'Method not found', 'request');
    }

    public function testMethodNamesAreCaseSensitive(): void
    {
        $response = $this->decode($this->server->handle('{"jsonrpc":"2.0","method":"Echo","id":1}'));

        $this->assertError($response, -32601, 'Method not found', 1);
    }

    public function testExecutesNotificationsWithoutReturningResponses(): void
    {
        $calls = [];
        $server = $this->createServer([
            'record' => new CallableMethodHandler(static function (array|stdClass $params) use (&$calls): string {
                $calls[] = $params;

                return 'discarded';
            }),
        ]);

        self::assertNull($server->handle('{"jsonrpc":"2.0","method":"record","params":[1,2]}'));
        self::assertSame([[1, 2]], $calls);
        self::assertNull($server->handle('{"jsonrpc":"2.0","method":"unknown"}'));
    }

    public function testNotificationResultsAreNotSerialized(): void
    {
        $result = new class implements JsonSerializable {
            public function jsonSerialize(): mixed
            {
                throw new RuntimeException('Notification results must be discarded.');
            }
        };
        $server = $this->createServer(['notify' => new CallableMethodHandler(static fn(): mixed => $result)]);

        self::assertNull($server->handle('{"jsonrpc":"2.0","method":"notify"}'));
    }

    public function testProcessesMixedBatchAndOmitsOnlyNotificationResponses(): void
    {
        $response = $this->decode($this->server->handle('[
            {"jsonrpc":"2.0","method":"echo","params":[1],"id":1},
            {"jsonrpc":"2.0","method":"echo","params":[2]},
            {"jsonrpc":"2.0","method":"unknown","id":"missing"},
            42,
            {"jsonrpc":"2.0","method":"echo","params":{},"id":null}
        ]'));

        self::assertIsArray($response);
        self::assertCount(4, $response);
        self::assertEquals((object) ['jsonrpc' => '2.0', 'result' => [1], 'id' => 1], $response[0]);
        $this->assertError($response[1], -32601, 'Method not found', 'missing');
        $this->assertError($response[2], -32600, 'Invalid Request');
        self::assertEquals((object) ['jsonrpc' => '2.0', 'result' => new stdClass(), 'id' => null], $response[3]);
    }

    public function testBatchContainingOneRequestStillReturnsArray(): void
    {
        self::assertSame(
            '[{"jsonrpc":"2.0","result":[],"id":1}]',
            $this->server->handle('[{"jsonrpc":"2.0","method":"echo","id":1}]'),
        );
    }

    public function testEmptyBatchReturnsSingleErrorObject(): void
    {
        $this->assertError($this->decode($this->server->handle('[]')), -32600, 'Invalid Request');
    }

    public function testNestedBatchIsInvalidAndDoesNotDispatchItsContents(): void
    {
        $response = $this->decode($this->server->handle('[[{"jsonrpc":"2.0","method":"echo","id":1}]]'));

        self::assertIsArray($response);
        self::assertCount(1, $response);
        $this->assertError($response[0], -32600, 'Invalid Request');
    }

    public function testAllNotificationBatchExecutesInOrderAndReturnsNothing(): void
    {
        $calls = [];
        $server = $this->createServer([
            'record' => new CallableMethodHandler(static function (array|stdClass $params) use (&$calls): void {
                $calls[] = $params;
            }),
        ]);

        self::assertNull($server->handle('[
            {"jsonrpc":"2.0","method":"record","params":[1]},
            {"jsonrpc":"2.0","method":"unknown"},
            {"jsonrpc":"2.0","method":"record","params":[2]}
        ]'));
        self::assertSame([[1], [2]], $calls);
    }

    public function testReturnsExplicitMethodErrorsWithoutExposingPreviousFailure(): void
    {
        $server = $this->createServer([
            'fail' => new CallableMethodHandler(static function (): never {
                throw new MethodCallException(
                    ErrorCode::InvalidParams->value,
                    'A required argument is missing.',
                    ['argument' => 'name'],
                    new RuntimeException('secret internal failure'),
                );
            }),
        ]);

        $response = $this->decode($server->handle('{"jsonrpc":"2.0","method":"fail","id":1}'));

        self::assertEquals((object) [
            'jsonrpc' => '2.0',
            'error' => (object) [
                'code' => -32602,
                'message' => 'A required argument is missing.',
                'data' => (object) ['argument' => 'name'],
            ],
            'id' => 1,
        ], $response);
    }

    public function testSupportsApplicationErrorCodesAndOmitsAbsentData(): void
    {
        $server = $this->createServer([
            'fail' => new CallableMethodHandler(static function (): never {
                throw new MethodCallException(1001, 'Article is unavailable.');
            }),
        ]);

        $this->assertError(
            $this->decode($server->handle('{"jsonrpc":"2.0","method":"fail","id":1}')),
            1001,
            'Article is unavailable.',
            1,
        );
        self::assertNull($server->handle('{"jsonrpc":"2.0","method":"fail"}'));
    }

    #[DataProvider('unexpectedFailures')]
    public function testTranslatesUnexpectedFailuresWithoutExposingDetails(Throwable $failure): void
    {
        $server = $this->createServer([
            'fail' => new CallableMethodHandler(static function () use ($failure): never {
                throw $failure;
            }),
        ]);

        $this->assertError(
            $this->decode($server->handle('{"jsonrpc":"2.0","method":"fail","id":"failure"}')),
            -32603,
            'Internal error',
            'failure',
        );
        self::assertNull($server->handle('{"jsonrpc":"2.0","method":"fail"}'));
    }

    /** @return iterable<array{Throwable}> */
    public static function unexpectedFailures(): iterable
    {
        yield [new RuntimeException('secret')];
        yield [new TypeError('secret')];
        yield [new Error('secret')];
    }

    #[DataProvider('results')]
    public function testAllowsAnyJsonResultType(mixed $result): void
    {
        $server = $this->createServer(['value' => new CallableMethodHandler(static fn(): mixed => $result)]);

        $response = $this->decode($server->handle('{"jsonrpc":"2.0","method":"value","id":1}'));

        self::assertObjectHasProperty('result', $response);
        self::assertEquals($result, $response->result);
        self::assertObjectNotHasProperty('error', $response);
    }

    /** @return iterable<array{mixed}> */
    public static function results(): iterable
    {
        yield [null];
        yield [false];
        yield [0];
        yield [''];
        yield [[1, 'two']];
        yield [new stdClass()];
        yield [(object) ['value' => 'example']];
    }

    public function testEncodingFailureDoesNotPreventOtherBatchCalls(): void
    {
        $calls = 0;
        $server = $this->createServer([
            'value' => new CallableMethodHandler(static function () use (&$calls): mixed {
                return ++$calls === 1 ? INF : 'ok';
            }),
        ]);

        $response = $this->decode($server->handle('[
            {"jsonrpc":"2.0","method":"value","id":1},
            {"jsonrpc":"2.0","method":"value","id":2}
        ]'));

        self::assertSame(2, $calls);
        $this->assertError($response[0], -32603, 'Internal error', 1);
        self::assertSame('ok', $response[1]->result);
    }

    public function testEncodesEachBatchResponseBeforeExecutingTheNextHandler(): void
    {
        $events = (object) ['entries' => []];
        $value = new class ($events) implements JsonSerializable {
            public function __construct(private stdClass $events) {}

            public function jsonSerialize(): mixed
            {
                $this->events->entries[] = 'serialize';

                return (object) ['value' => 'ok'];
            }
        };
        $server = $this->createServer([
            'value' => new CallableMethodHandler(static function () use ($events, $value): mixed {
                $events->entries[] = 'handle';

                return $value;
            }),
        ]);

        $response = $this->decode($server->handle('[
            {"jsonrpc":"2.0","method":"value","id":1},
            {"jsonrpc":"2.0","method":"value","id":2}
        ]'));

        self::assertSame(['handle', 'serialize', 'handle', 'serialize'], $events->entries);
        self::assertSame('ok', $response[0]->result->value);
        self::assertSame('ok', $response[1]->result->value);
    }

    public function testSerializationFailureDoesNotRetryOtherResponses(): void
    {
        $value = new class implements JsonSerializable {
            private int $calls = 0;

            public function jsonSerialize(): mixed
            {
                if (++$this->calls > 1) {
                    throw new Error('Serializer invoked more than once.');
                }

                return (object) ['value' => 'retained'];
            }
        };
        $server = $this->createServer([
            'good' => new CallableMethodHandler(static fn(): mixed => $value),
            'bad' => new CallableMethodHandler(static fn(): float => INF),
        ]);

        $response = $this->decode($server->handle('[
            {"jsonrpc":"2.0","method":"good","id":1},
            {"jsonrpc":"2.0","method":"bad","id":2}
        ]'));

        self::assertSame('retained', $response[0]->result->value);
        $this->assertError($response[1], -32603, 'Internal error', 2);
    }

    public function testFailedBatchReplacesEveryUnencodableResponseAndPreservesValidOnes(): void
    {
        $deep = 'leaf';
        for ($level = 0; $level < 520; ++$level) {
            $deep = [$deep];
        }
        $server = $this->createServer([
            'deep' => new CallableMethodHandler(static fn(): mixed => $deep),
            'invalidText' => new CallableMethodHandler(static fn(): string => "\xff"),
            'good' => new CallableMethodHandler(static fn(): mixed => (object) ['value' => 0.0]),
            'error' => new CallableMethodHandler(static function (): never {
                throw new MethodCallException(1001, 'Public failure', INF);
            }),
        ]);

        $response = $this->decode($server->handle('[
            {"jsonrpc":"2.0","method":"deep","id":1},
            {"jsonrpc":"2.0","method":"invalidText","id":2},
            {"jsonrpc":"2.0","method":"good","id":3},
            {"jsonrpc":"2.0","method":"error","id":4}
        ]'));

        $this->assertError($response[0], -32603, 'Internal error', 1);
        $this->assertError($response[1], -32603, 'Internal error', 2);
        self::assertSame(0.0, $response[2]->result->value);
        $this->assertError($response[3], -32603, 'Internal error', 4);
    }

    public function testHandlerFailuresDoNotPreventRemainingBatchCalls(): void
    {
        $calls = [];
        $server = $this->createServer([
            'record' => new CallableMethodHandler(static function (array|stdClass $params) use (&$calls): mixed {
                $calls[] = $params;

                return $params;
            }),
            'reject' => new CallableMethodHandler(static function (): never {
                throw new MethodCallException(ErrorCode::InvalidParams->value, 'Invalid arguments');
            }),
            'fail' => new CallableMethodHandler(static function (): never {
                throw new TypeError('secret');
            }),
        ]);

        $response = $this->decode($server->handle('[
            {"jsonrpc":"2.0","method":"record","params":[1],"id":1},
            {"jsonrpc":"2.0","method":"reject","id":2},
            {"jsonrpc":"2.0","method":"fail","id":3},
            {"jsonrpc":"2.0","method":"fail"},
            {"jsonrpc":"2.0","method":"record","params":[2],"id":4}
        ]'));

        self::assertSame([[1], [2]], $calls);
        self::assertCount(4, $response);
        self::assertSame([1], $response[0]->result);
        $this->assertError($response[1], -32602, 'Invalid arguments', 2);
        $this->assertError($response[2], -32603, 'Internal error', 3);
        self::assertSame([2], $response[3]->result);
    }

    #[DataProvider('identifiers')]
    public function testSerializerErrorsBecomeInternalErrorsWithTheOriginalIdentifier(float|int|string|null $id): void
    {
        $value = new class implements JsonSerializable {
            public function jsonSerialize(): mixed
            {
                throw new Error('secret serializer failure');
            }
        };
        $server = $this->createServer(['value' => new CallableMethodHandler(static fn(): mixed => $value)]);
        $request = json_encode(
            ['jsonrpc' => '2.0', 'method' => 'value', 'id' => $id],
            JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
        );

        $this->assertError(
            $this->decode($server->handle($request)),
            -32603,
            'Internal error',
            $id,
        );
    }

    public function testUnencodablePublicErrorDataBecomesInternalError(): void
    {
        $server = $this->createServer([
            'fail' => new CallableMethodHandler(static function (): never {
                throw new MethodCallException(1001, 'Public failure', INF);
            }),
        ]);

        $this->assertError(
            $this->decode($server->handle('{"jsonrpc":"2.0","method":"fail","id":1}')),
            -32603,
            'Internal error',
            1,
        );
    }

    #[DataProvider('invalidRegistrations')]
    public function testRejectsInvalidMethodRegistrations(string $name, mixed $handler): void
    {
        $this->expectException(InvalidMethodRegistrationException::class);
        $this->createServer([$name => $handler]);
    }

    /** @return iterable<array{string, mixed}> */
    public static function invalidRegistrations(): iterable
    {
        yield ['echo', null];
        yield ['echo', new stdClass()];
        yield ['echo', static fn(): null => null];
        yield ['rpc.example', new CallableMethodHandler(static fn(): null => null)];
    }

    public function testRegistrationFailuresImplementComponentExceptionContract(): void
    {
        $this->expectException(JsonRpcException::class);
        $this->createServer(['invalid' => new stdClass()]);
    }

    public function testRegistrationArrayReferencesCannotReplaceHandlers(): void
    {
        $handler = new CallableMethodHandler(static fn(): string => 'original');
        $methods = ['value' => &$handler];
        $server = $this->createServer($methods);
        $handler = new CallableMethodHandler(static fn(): string => 'replacement');

        self::assertSame(
            'original',
            $this->decode($server->handle('{"jsonrpc":"2.0","method":"value","id":1}'))->result,
        );
    }

    public function testSupportsNumericAndEmptyMethodNames(): void
    {
        $handler = new CallableMethodHandler(static fn(): string => 'ok');
        $server = $this->createServer(['123' => $handler, '' => $handler]);

        self::assertSame('ok', $this->decode($server->handle('{"jsonrpc":"2.0","method":"123","id":1}'))->result);
        self::assertSame('ok', $this->decode($server->handle('{"jsonrpc":"2.0","method":"","id":1}'))->result);
    }

    /** @param array<array-key, mixed> $methods */
    private function createServer(array $methods): DefaultJsonRpcServer
    {
        return new DefaultJsonRpcServer(
            new MessageDecoder(),
            new RegisteredMethodDispatcher($methods),
            new MessageEncoder(),
        );
    }

    private function decode(?string $response): mixed
    {
        self::assertIsString($response);

        return json_decode($response, flags: JSON_THROW_ON_ERROR);
    }

    private function assertError(
        stdClass $response,
        int $code,
        string $message,
        float|int|string|null $id = null,
    ): void {
        self::assertEquals((object) [
            'jsonrpc' => '2.0', 'error' => (object) ['code' => $code, 'message' => $message], 'id' => $id,
        ], $response);
    }
}
