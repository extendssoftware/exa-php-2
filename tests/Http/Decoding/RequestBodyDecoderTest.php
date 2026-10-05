<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Decoding;

use ExtendsSoftware\ExaPHP\Http\Message\Body\Body;
use ExtendsSoftware\ExaPHP\Http\Message\Body\StringBody;
use ExtendsSoftware\ExaPHP\Http\Message\Exception\BodyReadException;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\InvalidRequestBodyDecoderException;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\MalformedRequestBodyException;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\RequestBodyTooLargeException;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\UnsupportedRequestMediaTypeException;
use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Decoding\ContentTypeRequestBodyDecoder;
use ExtendsSoftware\ExaPHP\Http\Decoding\JsonRequestBodyDecoder;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class RequestBodyDecoderTest extends TestCase
{
    public function testPreservesObjectsArraysScalarsNullAndLargeIntegers(): void
    {
        $request = new Request(Method::Post, new Uri('/'), body: new StringBody(
            '{"object":{},"array":[],"null":null,"bool":true,"integer":123456789012345678901234567890}',
        ));
        $data = new JsonRequestBodyDecoder()->decode($request);
        $this->assertInstanceOf(stdClass::class, $data);
        $this->assertInstanceOf(stdClass::class, $data->object);
        $this->assertSame([], $data->array);
        $this->assertNull($data->null);
        $this->assertTrue($data->bool);
        $this->assertSame('123456789012345678901234567890', $data->integer);
        $this->assertNull(new JsonRequestBodyDecoder()->decode($request->withBody(new StringBody('null'))));
    }

    #[DataProvider('malformedBodies')]
    public function testRejectsMalformedJsonAndPreservesCause(string $bytes): void
    {
        try {
            new JsonRequestBodyDecoder()->decode(new Request(Method::Post, new Uri('/'), body: new StringBody($bytes)));
            $this->fail('Expected malformed body.');
        } catch (MalformedRequestBodyException $exception) {
            $this->assertInstanceOf(JsonException::class, $exception->getPrevious());
        }
    }

    /** @return iterable<array{string}> */
    public static function malformedBodies(): iterable
    {
        yield [''];
        yield [' '];
        yield ['{broken}'];
        yield ["\"\xFF\""];
        yield ['null true'];
    }

    public function testEnforcesActualByteLimitAcrossChunksAndStopsReading(): void
    {
        $body = $this->createStub(Body::class);
        $body->method('chunks')->willReturnCallback(function (): iterable {
            yield 'nu';
            yield 'll';
            $this->fail('Must stop after exceeding limit.');
        });
        $request = new Request(Method::Post, new Uri('/'), new Headers(['Content-Length' => '0']), $body);
        $this->expectException(RequestBodyTooLargeException::class);
        new JsonRequestBodyDecoder(3)->decode($request);
    }

    public function testBodyReadFailuresPropagateUnchanged(): void
    {
        $failure = new BodyReadException('Read failed');
        $body = $this->createStub(Body::class);
        $body->method('chunks')->willReturnCallback(static function () use ($failure): iterable {
            yield '{';
            throw $failure;
        });
        try {
            new JsonRequestBodyDecoder()->decode(new Request(Method::Post, new Uri('/'), body: $body));
            $this->fail('Expected read failure.');
        } catch (BodyReadException $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    public function testRejectsNegativeLimit(): void
    {
        $this->expectException(InvalidRequestBodyDecoderException::class);
        new JsonRequestBodyDecoder(-1);
    }

    /** @param array<array-key, mixed> $registrations */
    #[DataProvider('invalidRegistrations')]
    public function testRejectsInvalidDecoderRegistrations(array $registrations): void
    {
        $this->expectException(InvalidRequestBodyDecoderException::class);
        new ContentTypeRequestBodyDecoder($registrations);
    }

    /** @return iterable<array{array<array-key, mixed>}> */
    public static function invalidRegistrations(): iterable
    {
        yield [['application/json' => new stdClass()]];
        yield [['application/*' => new JsonRequestBodyDecoder()]];
        yield [['application/json;charset=utf-8' => new JsonRequestBodyDecoder()]];
        yield [[
            'application/json' => new JsonRequestBodyDecoder(),
            'Application/Json' => new JsonRequestBodyDecoder(),
        ]];
    }

    public function testAllowsBodyExactlyAtByteLimit(): void
    {
        $request = new Request(Method::Post, new Uri('/'), body: new StringBody('null'));
        $this->assertNull(new JsonRequestBodyDecoder(4)->decode($request));
    }

    #[DataProvider('contentTypes')]
    public function testSelectsJsonRegardlessOfAccept(string $type): void
    {
        $decoder = new ContentTypeRequestBodyDecoder(['application/json' => new JsonRequestBodyDecoder()]);
        $request = new Request(Method::Post, new Uri('/'), new Headers([
            'Content-Type' => $type, 'Accept' => 'application/xml',
        ]), new StringBody('[1,2]'));
        $this->assertSame([1, 2], $decoder->decode($request));
    }

    /** @return iterable<array{string}> */
    public static function contentTypes(): iterable
    {
        yield ['application/json'];
        yield ['Application/JSON; charset=utf-8'];
        yield ['application/json; charset="utf-8"'];
        yield ['application/json; profile="a;b,c"'];
    }

    /** @param array<string, string|list<string>> $headers */
    #[DataProvider('unsupportedTypes')]
    public function testRejectsUnsupportedMetadataBeforeReading(array $headers): void
    {
        $body = $this->createMock(Body::class);
        $body->expects($this->never())->method('chunks');
        $decoder = new ContentTypeRequestBodyDecoder(['application/json' => new JsonRequestBodyDecoder()]);
        $this->expectException(UnsupportedRequestMediaTypeException::class);
        $decoder->decode(new Request(Method::Post, new Uri('/'), new Headers($headers), $body));
    }

    /** @return iterable<array{array<string, string|list<string>>}> */
    public static function unsupportedTypes(): iterable
    {
        yield [[]];
        yield [['Content-Type' => '']];
        yield [['Content-Type' => 'application/xml']];
        yield [['Content-Type' => 'application/*']];
        yield [['Content-Type' => 'application/problem+json']];
        yield [['Content-Type' => ['application/json', 'application/json']]];
        yield [['Content-Type' => 'application/json, application/xml']];
        yield [['Content-Type' => 'application/json; broken']];
        yield [['Content-Type' => 'application/json; charset="broken']];
        yield [['Content-Type' => 'application/json', 'Content-Encoding' => 'gzip']];
    }
}
