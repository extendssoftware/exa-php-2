<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Decoding;

use Override;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\InvalidRequestBodyDecoderException;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\MalformedRequestBodyException;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\RequestBodyTooLargeException;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use JsonException;
use SensitiveParameter;

use function json_decode;
use function strlen;

use const JSON_BIGINT_AS_STRING;
use const JSON_THROW_ON_ERROR;

/**
 * Decodes UTF-8 JSON with objects and arrays preserved as distinct PHP types.
 */
final readonly class JsonRequestBodyDecoder implements RequestBodyDecoder
{
    /**
     * Creates a bounded JSON decoder.
     *
     * @param int $maxBytes Maximum body bytes buffered before decoding; defaults to one MiB.
     *
     * @throws InvalidRequestBodyDecoderException When the limit is negative.
     */
    public function __construct(private int $maxBytes = 1048576)
    {
        if ($maxBytes < 0) {
            throw new InvalidRequestBodyDecoderException('JSON body size limit must not be negative.');
        }
    }

    /**
     * {@inheritDoc}
     *
     * Empty input is malformed; null is valid. Integers outside PHP's integer range become strings. Nesting is limited
     * to 512 levels. Actual bytes enforce the size limit; `Content-Length` is not trusted. Reads are not retried.
     * The caller must select JSON decoding; content-type dispatch belongs to `ContentTypeRequestBodyDecoder`.
     *
     * @throws MalformedRequestBodyException When JSON is empty, malformed, invalid UTF-8, or exceeds nesting depth.
     * @throws RequestBodyTooLargeException When the body exceeds the byte limit.
     */
    #[Override]
    public function decode(#[SensitiveParameter] Request $request): mixed
    {
        $content = '';
        $length = 0;
        foreach ($request->body->chunks() as $chunk) {
            $bytes = strlen($chunk);
            if ($bytes > $this->maxBytes - $length) {
                throw new RequestBodyTooLargeException('JSON request body exceeds the configured byte limit.');
            }
            $length += $bytes;
            $content .= $chunk;
        }
        try {
            return json_decode($content, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $exception) {
            throw new MalformedRequestBodyException('Request body is not valid JSON.', 0, $exception);
        }
    }
}
