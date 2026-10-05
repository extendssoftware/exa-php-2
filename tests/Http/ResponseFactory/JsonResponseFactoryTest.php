<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\ResponseFactory;

use ExtendsSoftware\ExaPHP\Http\Exception\ResponseEncodingException;
use ExtendsSoftware\ExaPHP\Http\Headers;
use ExtendsSoftware\ExaPHP\Http\ResponseFactory\JsonResponseFactory;
use ExtendsSoftware\ExaPHP\Http\StatusCode;
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
        $this->assertSame(StatusCode::Created, $response->statusCode);
        $this->assertSame(['application/json'], $response->headers->get('Content-Type'));
        $this->assertSame([], $response->headers->get('Content-Length'));
        $this->assertSame([], $response->headers->get('Transfer-Encoding'));
        $this->assertSame(['yes'], $response->headers->get('X-Test'));
        $this->assertSame('{"id":1}', implode('', iterator_to_array($response->body->chunks())));
    }

    public function testEncodingFailurePreservesCause(): void
    {
        try {
            new JsonResponseFactory()->create("\xFF");
            $this->fail('Expected encoding failure.');
        } catch (ResponseEncodingException $exception) {
            $this->assertInstanceOf(JsonException::class, $exception->getPrevious());
        }
    }
}
