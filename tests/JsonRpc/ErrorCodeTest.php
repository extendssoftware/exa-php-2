<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\JsonRpc;

use ExtendsSoftware\ExaPHP\JsonRpc\ErrorCode;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\MessageEncoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ErrorCodeTest extends TestCase
{
    public function testDefaultsToAbsentData(): void
    {
        self::assertNull(ErrorCode::InvalidParams->response()->error->data);
    }

    public function testAcceptsExplicitNullData(): void
    {
        self::assertNull(ErrorCode::InvalidParams->response(data: null)->error->data);
    }

    #[DataProvider('errorData')]
    public function testIncludesSuppliedDataWithoutChangingItsType(mixed $data): void
    {
        $response = ErrorCode::InvalidParams->response(id: 1, data: $data);

        self::assertObjectHasProperty('data', $response->error);
        self::assertSame($data, $response->error->data);
    }

    /** @return iterable<string, array{mixed}> */
    public static function errorData(): iterable
    {
        yield 'false' => [false];
        yield 'zero' => [0];
        yield 'float' => [1.5];
        yield 'empty string' => [''];
        yield 'empty array' => [[]];
        yield 'list' => [['name', 'required']];
        yield 'object' => [new stdClass()];
        yield 'structured details' => [['field' => 'name', 'reason' => 'required']];
    }

    public function testSerializesDataInsideTheErrorObject(): void
    {
        $response = ErrorCode::InvalidParams->response(id: 1, data: ['field' => 'name', 'reason' => 'required']);

        self::assertSame(
            '{"jsonrpc":"2.0","error":{"code":-32602,"message":"Invalid params",'
            . '"data":{"field":"name","reason":"required"}},"id":1}',
            new MessageEncoder()->encode($response),
        );
    }
}
