<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\ErrorHandling\ProblemDetails;

use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetails;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetailsResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\Message\ProtocolVersion;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Representation\Exception\ResponseEncodingException;
use PHPUnit\Framework\TestCase;

use function implode;
use function iterator_to_array;
use function json_decode;

final class ProblemDetailsResponseFactoryTest extends TestCase
{
    public function testMatchesStatusAndRetainsProtocolAndRequiredHeaders(): void
    {
        $response = new ProblemDetailsResponseFactory()->create(
            new ProblemDetails(StatusCode::MethodNotAllowed, 'Method Not Allowed'),
            new Headers(['Allow' => 'GET', 'Content-Type' => 'text/plain', 'Content-Length' => '4',
                'Transfer-Encoding' => 'chunked']),
            ProtocolVersion::Http2,
        );
        $this->assertSame(StatusCode::MethodNotAllowed, $response->statusCode);
        $this->assertSame(ProtocolVersion::Http2, $response->protocolVersion);
        $this->assertSame(['GET'], $response->headers->get('Allow'));
        $this->assertSame(['application/problem+json'], $response->headers->get('Content-Type'));
        $this->assertSame(['no-store'], $response->headers->get('Cache-Control'));
        $this->assertSame([], $response->headers->get('Content-Length'));
        $this->assertSame([], $response->headers->get('Transfer-Encoding'));
        $this->assertSame(['type' => 'about:blank', 'title' => 'Method Not Allowed', 'status' => 405],
            json_decode(implode('', iterator_to_array($response->body->chunks())), true));
    }

    public function testInvalidUtf8FailsWithoutPartialResponse(): void
    {
        $this->expectException(ResponseEncodingException::class);
        new ProblemDetailsResponseFactory()->create(new ProblemDetails(StatusCode::BadRequest, "\xff"));
    }
}
