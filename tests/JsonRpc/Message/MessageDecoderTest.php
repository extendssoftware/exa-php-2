<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\JsonRpc\Message;

use ExtendsSoftware\ExaPHP\JsonRpc\Message\ErrorResponse;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\MessageDecoder;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\Notification;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class MessageDecoderTest extends TestCase
{
    public function testDistinguishesNullRequestIdentifierFromNotification(): void
    {
        $decoder = new MessageDecoder();
        $request = $decoder->decode('{"jsonrpc":"2.0","method":"example","id":null}');
        $notification = $decoder->decode('{"jsonrpc":"2.0","method":"example"}');

        self::assertInstanceOf(Request::class, $request);
        self::assertNull($request->id);
        self::assertSame('example', $request->method);
        self::assertInstanceOf(Notification::class, $notification);
        self::assertSame('example', $notification->method);
    }

    public function testPreservesOmittedPositionalAndNamedParameters(): void
    {
        $messages = new MessageDecoder()->decode('[
            {"jsonrpc":"2.0","method":"example","id":1},
            {"jsonrpc":"2.0","method":"example","params":[],"id":2},
            {"jsonrpc":"2.0","method":"example","params":{},"id":3}
        ]');

        self::assertIsArray($messages);
        self::assertCount(3, $messages);
        self::assertNull($messages[0]->params);
        self::assertSame([], $messages[1]->params);
        self::assertInstanceOf(stdClass::class, $messages[2]->params);
    }

    public function testKeepsInvalidEntriesInTheirBatchPositions(): void
    {
        $messages = new MessageDecoder()->decode('[
            {"jsonrpc":"2.0","method":"example","id":1},
            42,
            {"jsonrpc":"2.0","method":"example"},
            [{"jsonrpc":"2.0","method":"nested","id":2}]
        ]');

        self::assertIsArray($messages);
        self::assertInstanceOf(Request::class, $messages[0]);
        self::assertInstanceOf(ErrorResponse::class, $messages[1]);
        self::assertSame(-32600, $messages[1]->error->code);
        self::assertNull($messages[1]->id);
        self::assertInstanceOf(Notification::class, $messages[2]);
        self::assertInstanceOf(ErrorResponse::class, $messages[3]);
    }

    #[DataProvider('invalidDocuments')]
    public function testReturnsTypedErrorsForInvalidDocuments(string $json, int $code): void
    {
        $response = new MessageDecoder()->decode($json);

        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertSame($code, $response->error->code);
        self::assertNull($response->id);
    }

    /** @return iterable<array{string, int}> */
    public static function invalidDocuments(): iterable
    {
        yield ['{', -32700];
        yield ['[]', -32600];
        yield ['null', -32600];
        yield ['{"jsonrpc":"2.0","method":"example","params":null,"id":1}', -32600];
        yield ['{"jsonrpc":"2.0","method":"example","id":true}', -32600];
    }
}
