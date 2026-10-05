<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Server;

use ErrorException;
use ExtendsSoftware\ExaPHP\Http\Message\Body\Body;
use ExtendsSoftware\ExaPHP\Http\Message\Body\StreamBody;
use ExtendsSoftware\ExaPHP\Http\Server\Exception\RequestCreationException;
use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\HttpException;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\ProtocolVersion;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;

use function fopen;
use function is_string;
use function restore_error_handler;
use function set_error_handler;
use function str_replace;
use function str_starts_with;
use function substr;
use function strtolower;

use const E_WARNING;

/**
 * Adapts PHP server variables and php://input without parsing or buffering the request body.
 *
 * Request targets retain their encoded representation. Origin targets use Host and the server HTTPS flag when
 * available; forwarded headers are never trusted. Without Host, single-slash origin targets remain relative.
 * Headers reflect PHP's server variables, which may already combine repeated fields. Proxy trust, parsed forms,
 * cookies, and uploaded files are outside this adapter.
 */
final readonly class PhpServerRequestFactory implements ServerRequestFactory
{
    /**
     * Creates the current request using PHP's server variables and input stream.
     *
     * @return Request The incoming request with a single-use stream body.
     *
     * @throws HttpException When server metadata is invalid or the input stream cannot be opened.
     */
    public function create(): Request
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }, E_WARNING);
        try {
            $stream = fopen('php://input', 'rb');
        } catch (ErrorException $exception) {
            throw new RequestCreationException('Could not open the request input stream.', 0, $exception);
        } finally {
            restore_error_handler();
        }
        if ($stream === false) {
            throw new RequestCreationException('Could not open the request input stream.');
        }

        return $this->fromServer($_SERVER, new StreamBody($stream));
    }

    /**
     * Creates a request from an explicit PHP server snapshot and body without consuming either.
     *
     * @param array<string, mixed> $server PHP server variables, including method, target, and protocol.
     * @param Body $body The incoming body.
     *
     * @return Request The adapted request.
     *
     * @throws HttpException When required metadata is missing, unsupported, or malformed.
     */
    public function fromServer(array $server, Body $body): Request
    {
        $method = Method::tryFrom($this->required($server, 'REQUEST_METHOD'));
        $protocol = match ($this->required($server, 'SERVER_PROTOCOL')) {
            'HTTP/1.0' => ProtocolVersion::Http10,
            'HTTP/1.1' => ProtocolVersion::Http11,
            'HTTP/2', 'HTTP/2.0' => ProtocolVersion::Http2,
            'HTTP/3', 'HTTP/3.0' => ProtocolVersion::Http3,
            default => null,
        };
        if ($method === null || $protocol === null) {
            throw new RequestCreationException('Unsupported request method or HTTP protocol.');
        }
        $target = $this->required($server, 'REQUEST_URI');
        $uri = match (true) {
            $method === Method::Connect => new Uri('//' . $target),
            str_starts_with($target, '/') => $this->originUri($target, $server),
            default => new Uri($target),
        };
        if ($method !== Method::Connect && $target !== '*' && !str_starts_with($target, '/')
            && $uri->scheme() === null) {
            throw new RequestCreationException(
                'Request targets must be origin-form, absolute-form, or OPTIONS asterisk.',
            );
        }
        $headers = [];
        foreach ($server as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                if (!is_string($value)) {
                    throw new RequestCreationException('Server header values must be strings.');
                }
                $headers[str_replace('_', '-', substr($name, 5))] = $value;
            }
        }
        foreach (['CONTENT_TYPE', 'CONTENT_LENGTH'] as $name) {
            if (isset($server[$name])) {
                if (!is_string($server[$name])) {
                    throw new RequestCreationException('Server header values must be strings.');
                }
                $headers[str_replace('_', '-', $name)] = $server[$name];
            }
        }

        return new Request($method, $uri, new Headers($headers), $body, $protocol);
    }

    /**
     * Builds an origin URI without treating a leading double slash as an authority.
     *
     * @param non-empty-string $target The origin-form request target.
     * @param array<string, mixed> $server The server snapshot.
     *
     * @return Uri The request URI.
     *
     * @throws HttpException When Host is malformed or a double-slash target has no Host.
     */
    private function originUri(string $target, array $server): Uri
    {
        if (!isset($server['HTTP_HOST'])) {
            if (str_starts_with($target, '//')) {
                throw new RequestCreationException('Double-slash origin targets require a Host header.');
            }

            return new Uri($target);
        }
        $host = $this->required($server, 'HTTP_HOST');
        $https = $server['HTTPS'] ?? '';
        $scheme = is_string($https) && $https !== '' && $https !== '0' && strtolower($https) !== 'off'
            ? 'https' : 'http';
        $authority = new Uri($scheme . '://' . $host);
        if ($authority->host() === null || $authority->host() === '' || $authority->path() !== ''
            || $authority->userInfo() !== null || $authority->query() !== null || $authority->fragment() !== null) {
            throw new RequestCreationException('Host must contain only a host and optional port.');
        }

        return new Uri($scheme . '://' . $host . $target);
    }

    /**
     * Reads a required non-empty server variable.
     *
     * @param array<string, mixed> $server The server snapshot.
     * @param string $name The variable name.
     *
     * @return non-empty-string The variable value.
     *
     * @throws RequestCreationException When the variable is missing or invalid.
     */
    private function required(array $server, string $name): string
    {
        $value = $server[$name] ?? null;
        if (!is_string($value) || $value === '') {
            throw new RequestCreationException('Missing or invalid server variable: ' . $name . '.');
        }

        return $value;
    }
}
