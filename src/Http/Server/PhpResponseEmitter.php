<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Server;

use Override;
use ErrorException;
use ExtendsSoftware\ExaPHP\Http\Server\Exception\ResponseEmissionException;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\Response;

use function header;
use function header_remove;
use function headers_sent;
use function http_response_code;
use function in_array;
use function restore_error_handler;
use function set_error_handler;

use const E_WARNING;

/**
 * Emits a final response through PHP's SAPI headers and output channel.
 *
 * The SAPI controls wire protocol and transfer framing. Existing queued headers are replaced. Output buffering is
 * left to the host application; emission neither flushes buffers nor terminates execution. Body failures may leave
 * a partial response. Informational responses and successful CONNECT tunnels require a different server adapter.
 */
final readonly class PhpResponseEmitter implements ResponseEmitter
{
    /**
     * {@inheritDoc}
     *
     * `HEAD`, 204, 205, and 304 responses never consume the body. `Transfer-Encoding` is rejected.
     * `Content-Length` is omitted for 204 and forced to zero for 205; other content lengths remain explicit.
     *
     * @throws ResponseEmissionException When headers were sent or the response cannot be emitted by this adapter.
     */
    #[Override]
    public function emit(Response $response, Method $requestMethod): void
    {
        $status = $response->statusCode->value;
        if ($status < 200 || ($requestMethod === Method::Connect && $status < 300)) {
            throw new ResponseEmissionException('Informational responses and CONNECT tunnels are unsupported.');
        }
        if ($response->headers->has('Transfer-Encoding')) {
            throw new ResponseEmissionException('Transfer-Encoding must be managed by the PHP server.');
        }
        if (headers_sent()) {
            throw new ResponseEmissionException('Response headers have already been sent.');
        }
        $headers = match ($status) {
            204 => $response->headers->without('Content-Length'),
            205 => $response->headers->with('Content-Length', '0'),
            default => $response->headers,
        };
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }, E_WARNING);
        try {
            header_remove();
            foreach ($headers->all() as $name => $values) {
                foreach ($values as $value) {
                    header($name . ': ' . $value, false);
                }
            }
            // Set status last so PHP's special handling of Location cannot replace it.
            http_response_code($status);
        } catch (ErrorException $exception) {
            throw new ResponseEmissionException('Could not emit response headers.', 0, $exception);
        } finally {
            restore_error_handler();
        }
        if ($requestMethod === Method::Head || in_array($status, [204, 205, 304], true)) {
            return;
        }
        foreach ($response->body->chunks() as $chunk) {
            echo $chunk;
        }
    }
}
