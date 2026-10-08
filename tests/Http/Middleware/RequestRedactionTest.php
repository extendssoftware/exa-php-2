<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Middleware;

use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\MalformedRequestBodyException;
use ExtendsSoftware\ExaPHP\Http\Decoding\JsonRequestBodyDecoder;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Message\Body\StringBody;
use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use ExtendsSoftware\ExaPHP\Http\Middleware\MiddlewarePipeline;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;
use SensitiveParameterValue;

use function ini_set;

final class RequestRedactionTest extends TestCase
{
    public function testRedactsRequestsAcrossPipelineHandlerAndDecoderStackFrames(): void
    {
        $original = ini_set('zend.exception_ignore_args', '0');
        try {
            $request = new Request(
                Method::Post,
                new Uri('/'),
                new Headers(['Authorization' => 'Bearer secret-token', 'Content-Type' => 'application/json']),
                new StringBody('{"secret": invalid-json}'),
            );
            $handler = new class implements RequestHandler {
                public function handle(#[SensitiveParameter] Request $request): Response
                {
                    new JsonRequestBodyDecoder()->decode($request);

                    return new Response();
                }
            };
            try {
                new MiddlewarePipeline($handler)->handle($request);
                self::fail('Expected malformed JSON.');
            } catch (MalformedRequestBodyException $exception) {
                $frames = 0;
                foreach ($exception->getTrace() as $frame) {
                    if (($frame['class'] ?? null) === JsonRequestBodyDecoder::class
                        || ($frame['class'] ?? null) === $handler::class
                        || ($frame['class'] ?? null) === MiddlewarePipeline::class) {
                        self::assertInstanceOf(SensitiveParameterValue::class, $frame['args'][0]);
                        ++$frames;
                    }
                }
                self::assertSame(3, $frames);
                self::assertStringNotContainsString('secret-token', $exception->getTraceAsString());
            }
        } finally {
            if ($original !== false) {
                ini_set('zend.exception_ignore_args', $original);
            }
        }
    }
}
