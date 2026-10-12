<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Server;

use PHPUnit\Framework\TestCase;

use function explode;
use function fclose;
use function fgets;
use function fwrite;
use function proc_close;
use function proc_open;
use function proc_terminate;
use function stream_get_contents;
use function stream_set_timeout;
use function stream_socket_client;
use function stream_socket_get_name;
use function stream_socket_server;
use function strlen;
use function substr;

use const PHP_BINARY;

final class PhpServerE2ETest extends TestCase
{
    public function testEmitsRealResponsesFromPhpRequests(): void
    {
        $reservation = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($reservation, false);
        fclose($reservation);
        $process = proc_open([
            PHP_BINARY, '-d', 'output_buffering=0', '-S', $address, __DIR__ . '/Fixture/server.php',
        ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        try {
            stream_set_timeout($pipes[2], 5);
            $startup = fgets($pipes[2]);
            self::assertIsString($startup);
            self::assertStringContainsString('started', $startup);
            $response = $this->request($address, 'POST', '/echo?q=%2F', 'payload');
            self::assertStringContainsString('200 OK', $response);
            self::assertStringContainsString("Set-Cookie: a=1\r\n", $response);
            self::assertStringContainsString("Set-Cookie: b=2\r\n", $response);
            self::assertStringEndsWith('/echo?q=%2F|value|payload', $response);
            self::assertStringEndsWith('streamed response', $this->request($address, 'GET', '/'));
            self::assertStringContainsString('200 OK', $this->request($address, 'GET', '/location'));
            foreach (['/204', '/205', '/304', '/head'] as $path) {
                $response = $this->request($address, $path === '/head' ? 'HEAD' : 'GET', $path);
                $expectedStatus = $path === '/head' ? '200' : substr($path, 1);
                self::assertStringStartsWith('HTTP/1.1 ' . $expectedStatus . ' ', $response);
                self::assertSame('', explode("\r\n\r\n", $response, 2)[1]);
                if ($path === '/204') {
                    self::assertStringNotContainsString('Content-Length:', $response);
                }
                if ($path === '/205') {
                    self::assertStringContainsString('Content-Length: 0', $response);
                }
            }
            self::assertStringEndsWith('already sent|emission rejected', $this->request($address, 'GET', '/sent'));
        } finally {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
    }

    private function request(string $address, string $method, string $target, string $body = ''): string
    {
        $socket = stream_socket_client('tcp://' . $address);
        stream_set_timeout($socket, 5);
        try {
            fwrite($socket, $method . ' ' . $target . " HTTP/1.1\r\nHost: example.test\r\nX-Test: value\r\n"
                . 'Content-Length: ' . strlen($body) . "\r\nConnection: close\r\n\r\n" . $body);

            return stream_get_contents($socket);
        } finally {
            fclose($socket);
        }
    }
}
