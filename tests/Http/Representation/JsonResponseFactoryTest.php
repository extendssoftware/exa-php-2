<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Representation;

use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Representation\Exception\ResponseEncodingException;
use ExtendsSoftware\ExaPHP\Http\Representation\JsonResponseFactory;
use JsonException;
use PHPUnit\Framework\TestCase;

use function implode;
use function iterator_to_array;

final class JsonResponseFactoryTest extends TestCase
{
    public function testEncodesDataAndReplacesStaleRepresentationHeaders(): void
    {
        $response = new JsonResponseFactory()->create(['id' => 1], StatusCode::Created, new Headers([
            'Content-Type' => 'text/plain',
            'Content-Length' => '999',
            'Transfer-Encoding' => 'chunked',
            'X-Test' => 'yes',
        ]));
        self::assertSame(StatusCode::Created, $response->statusCode);
        self::assertSame(['application/json'], $response->headers->get('Content-Type'));
        self::assertSame([], $response->headers->get('Content-Length'));
        self::assertSame([], $response->headers->get('Transfer-Encoding'));
        self::assertSame(['yes'], $response->headers->get('X-Test'));
        self::assertSame('{"id":1}', implode('', iterator_to_array($response->body->chunks())));
    }

    public function testEncodingFailurePreservesCause(): void
    {
        try {
            new JsonResponseFactory()->create("\xFF");
            self::fail('Expected encoding failure.');
        } catch (ResponseEncodingException $exception) {
            self::assertInstanceOf(JsonException::class, $exception->getPrevious());
        }
    }
}
