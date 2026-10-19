<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\JsonRpc\Method;

use ExtendsSoftware\ExaPHP\JsonRpc\ErrorCode;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\ErrorResponse;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\Notification;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\Request;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\ResultResponse;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\CallableMethodHandler;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\Exception\MethodCallException;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\RegisteredMethodDispatcher;
use JsonSerializable;
use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;

final class RegisteredMethodDispatcherTest extends TestCase
{
    public function testPassesNamedParametersToHandlerWithoutChangingTheirStructure(): void
    {
        $params = (object) ['name' => 'Ada'];
        $dispatcher = new RegisteredMethodDispatcher([
            'echo' => new CallableMethodHandler(static fn(array|stdClass $value): mixed => $value),
        ]);

        $response = $dispatcher->dispatch(new Request(null, 'echo', $params));

        self::assertInstanceOf(ResultResponse::class, $response);
        self::assertNull($response->id);
        self::assertSame($params, $response->result);
    }

    public function testSuppliesEmptyArgumentsWhenOmitted(): void
    {
        $dispatcher = new RegisteredMethodDispatcher([
            'echo' => new CallableMethodHandler(static fn(array|stdClass $value): mixed => $value),
        ]);

        self::assertSame([], $dispatcher->dispatch(new Request(1, 'echo'))->result);
    }

    public function testExecutesNotificationsWithoutProducingResponses(): void
    {
        $calls = 0;
        $dispatcher = new RegisteredMethodDispatcher([
            'notify' => new CallableMethodHandler(static function () use (&$calls): void {
                ++$calls;
            }),
        ]);

        self::assertNull($dispatcher->dispatch(new Notification('notify')));
        self::assertNull($dispatcher->dispatch(new Notification('unknown')));
        self::assertSame(1, $calls);
    }

    public function testReturnsTypedMethodNotFoundResponseForUnknownRequests(): void
    {
        $response = new RegisteredMethodDispatcher()->dispatch(new Request('request', 'unknown'));

        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertSame('request', $response->id);
        self::assertSame(ErrorCode::MethodNotFound->value, $response->error->code);
    }

    public function testReturnsPublicMethodErrorDetails(): void
    {
        $dispatcher = new RegisteredMethodDispatcher([
            'reject' => new CallableMethodHandler(static function (): never {
                throw new MethodCallException(1001, 'Public failure', ['field' => 'name']);
            }),
        ]);

        $response = $dispatcher->dispatch(new Request(1, 'reject'));

        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertSame(1001, $response->error->code);
        self::assertSame('Public failure', $response->error->message);
        self::assertSame(['field' => 'name'], $response->error->data);
    }

    public function testTranslatesEngineFailuresAndSuppressesNotificationErrors(): void
    {
        $dispatcher = new RegisteredMethodDispatcher([
            'fail' => new CallableMethodHandler(static function (): never {
                throw new TypeError('secret');
            }),
        ]);

        $response = $dispatcher->dispatch(new Request(1, 'fail'));

        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertSame(-32603, $response->error->code);
        self::assertSame('Internal error', $response->error->message);
        self::assertNull($dispatcher->dispatch(new Notification('fail')));
    }

    public function testReturnsResultsWithoutInvokingTheirSerializers(): void
    {
        $result = new class implements JsonSerializable {
            public int $calls = 0;

            public function jsonSerialize(): mixed
            {
                ++$this->calls;

                return 'serialized';
            }
        };
        $dispatcher = new RegisteredMethodDispatcher([
            'value' => new CallableMethodHandler(static fn(): mixed => $result),
        ]);

        $response = $dispatcher->dispatch(new Request(1, 'value'));

        self::assertInstanceOf(ResultResponse::class, $response);
        self::assertSame($result, $response->result);
        self::assertSame(0, $result->calls);
    }
}
