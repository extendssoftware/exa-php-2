<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Decoding;

use Override;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\InvalidRequestBodyDecoderException;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\UnsupportedRequestMediaTypeException;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use SensitiveParameter;

use function count;
use function is_string;
use function preg_match;
use function strtolower;
use function trim;

/**
 * Selects a registered decoder from one Content-Type field without defaulting missing metadata.
 *
 * Parameter names and syntax are validated but parameters are left to the selected decoder's policy. Non-identity
 * content encodings are unsupported. No body bytes are read until the media type has selected a decoder.
 */
final readonly class ContentTypeRequestBodyDecoder implements RequestBodyDecoder
{
    /**
     * Decoders indexed by normalized media type.
     *
     * @var array<string, RequestBodyDecoder>
     */
    private array $decoders;

    /**
     * Creates a dispatcher with exact, parameterless media type registrations.
     *
     * @param array<string, RequestBodyDecoder> $decoders Available media types and their decoders.
     *
     * @throws InvalidRequestBodyDecoderException When a registration is invalid or repeated ignoring case.
     */
    public function __construct(array $decoders)
    {
        $normalized = [];
        foreach ($decoders as $type => $decoder) {
            if (!is_string($type) || !$decoder instanceof RequestBodyDecoder
                || preg_match('~\A[\w!#$%&\x27+.^`|\~-]+/[\w!#$%&\x27+.^`|\~-]+\z~', $type) !== 1
                || isset($normalized[strtolower($type)])) {
                throw new InvalidRequestBodyDecoderException(
                    'Decoders require unique concrete media type registrations.',
                );
            }
            $normalized[strtolower($type)] = $decoder;
        }
        $this->decoders = $normalized;
    }

    /**
     * {@inheritDoc}
     *
     * Structured suffix types require explicit registration. Decode encoded bodies and remove `Content-Encoding`
     * before calling this decoder.
     *
     * @throws UnsupportedRequestMediaTypeException When content type or content encoding is unsupported or malformed.
     */
    #[Override]
    public function decode(#[SensitiveParameter] Request $request): mixed
    {
        $types = $request->headers->get('Content-Type');
        $token = '[\w!#$%&\x27*+.^`|\~-]+';
        $quoted = '"(?:[^"\\\\\x00-\x1F\x7F]|\\\\[\x20-\x7E])*"';
        $pattern = '~\A(' . $token . '/' . $token . ')(?:[ \t]*;[ \t]*' . $token
            . '[ \t]*=[ \t]*(?:' . $token . '|' . $quoted . '))*[ \t]*\z~';
        if (count($types) !== 1 || preg_match($pattern, $types[0], $matches) !== 1) {
            throw new UnsupportedRequestMediaTypeException('A single valid Content-Type header is required.');
        }
        $type = strtolower($matches[1]);
        if (!isset($this->decoders[$type])) {
            throw new UnsupportedRequestMediaTypeException(
                'No request body decoder is registered for this media type.',
            );
        }
        foreach ($request->headers->get('Content-Encoding') as $encoding) {
            if (strtolower(trim($encoding)) !== 'identity') {
                throw new UnsupportedRequestMediaTypeException('Encoded request bodies are unsupported.');
            }
        }

        return $this->decoders[$type]->decode($request);
    }
}
