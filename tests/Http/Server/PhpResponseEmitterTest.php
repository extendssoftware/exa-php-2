<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Server;

use ExtendsSoftware\ExaPHP\Http\Body\Body;
use ExtendsSoftware\ExaPHP\Http\Exception\ResponseEmissionException;
use ExtendsSoftware\ExaPHP\Http\Headers;
use ExtendsSoftware\ExaPHP\Http\Method;
use ExtendsSoftware\ExaPHP\Http\Response;
use ExtendsSoftware\ExaPHP\Http\Server\PhpResponseEmitter;
use ExtendsSoftware\ExaPHP\Http\StatusCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhpResponseEmitterTest extends TestCase
{
    #[DataProvider('unsupportedResponses')]
    public function testRejectsUnsupportedEmissionWithoutReadingBody(StatusCode $status, Method $method): void
    {
        $body = $this->createMock(Body::class);
        $body->expects($this->never())->method('chunks');
        $this->expectException(ResponseEmissionException::class);
        new PhpResponseEmitter()->emit(new Response($status, body: $body), $method);
    }

    /** @return iterable<array{StatusCode, Method}> */
    public static function unsupportedResponses(): iterable
    {
        yield [StatusCode::Continue, Method::Get];
        yield [StatusCode::SwitchingProtocols, Method::Get];
        yield [StatusCode::Ok, Method::Connect];
    }

    public function testRejectsApplicationTransferFraming(): void
    {
        $this->expectException(ResponseEmissionException::class);
        new PhpResponseEmitter()->emit(
            new Response(headers: new Headers(['Transfer-Encoding' => 'chunked'])),
            Method::Get,
        );
    }
}
