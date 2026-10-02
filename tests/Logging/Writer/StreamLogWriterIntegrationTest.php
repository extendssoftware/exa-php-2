<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Logging\Writer;

use DateTimeImmutable;
use ErrorException;
use ExtendsSoftware\ExaPHP\Logging\Exception\InvalidLogStreamException;
use ExtendsSoftware\ExaPHP\Logging\Exception\LogFormattingException;
use ExtendsSoftware\ExaPHP\Logging\Exception\LogStreamOpenException;
use ExtendsSoftware\ExaPHP\Logging\Exception\LogStreamWriteException;
use ExtendsSoftware\ExaPHP\Logging\Formatter\LogFormatter;
use ExtendsSoftware\ExaPHP\Logging\LogLevel;
use ExtendsSoftware\ExaPHP\Logging\LogRecord;
use ExtendsSoftware\ExaPHP\Logging\Writer\StreamLogWriter;
use ExtendsSoftware\ExaPHP\Logging\WriterLogger;
use PHPUnit\Framework\TestCase;

use function fclose;
use function file;
use function file_get_contents;
use function file_put_contents;
use function fopen;
use function fwrite;
use function is_resource;
use function json_decode;
use function restore_error_handler;
use function rewind;
use function set_error_handler;
use function stream_get_contents;
use function sys_get_temp_dir;
use function stream_socket_pair;

use function tempnam;
use function unlink;

use const STREAM_PF_UNIX;
use const STREAM_SOCK_STREAM;
use const STREAM_IPPROTO_IP;

final class StreamLogWriterIntegrationTest extends TestCase
{
    public function testAppendsNdjsonToExistingFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'exa-log-');
        try {
            file_put_contents($path, "existing\n");
            $logger = new WriterLogger(new StreamLogWriter($path));
            $logger->log(LogLevel::Info, 'First', ['id' => 1]);
            $logger->log(LogLevel::Error, 'Second');
            $lines = file($path);
            $this->assertCount(3, $lines);
            $this->assertSame("existing\n", $lines[0]);
            $this->assertSame('First', json_decode($lines[1], true)['message']);
            $this->assertSame(['id' => 1], json_decode($lines[1], true)['context']);
            $this->assertSame('error', json_decode($lines[2], true)['level']);
        } finally {
            unlink($path);
        }
    }

    public function testAppendsExactFormatterBytesAndLeavesSuppliedStreamOpen(): void
    {
        $stream = fopen('php://memory', 'w+');
        try {
            fwrite($stream, 'existing');
            rewind($stream);
            $formatter = $this->createMock(LogFormatter::class);
            $record = new LogRecord(LogLevel::Info, 'Message', new DateTimeImmutable());
            $formatter->expects($this->once())->method('format')->with($record)->willReturn('formatted');
            $writer = new StreamLogWriter($stream, $formatter);
            $writer->write($record);
            unset($writer);
            $this->assertTrue(is_resource($stream));
            rewind($stream);
            $this->assertSame('existingformatted', stream_get_contents($stream));
        } finally {
            fclose($stream);
        }
    }

    public function testRejectsClosedStreamAtWriteTime(): void
    {
        $stream = fopen('php://memory', 'w+');
        $writer = new StreamLogWriter($stream);
        fclose($stream);
        $this->expectException(InvalidLogStreamException::class);
        $writer->write(new LogRecord(LogLevel::Info, 'Message', new DateTimeImmutable()));
    }

    public function testRejectsReadOnlyStream(): void
    {
        $stream = fopen(__FILE__, 'rb');
        try {
            $this->expectException(InvalidLogStreamException::class);
            new StreamLogWriter($stream);
        } finally {
            fclose($stream);
        }
    }

    public function testOpenFailurePreservesCauseAndRestoresErrorHandler(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'exa-log-');
        $handler = static fn(): bool => false;
        set_error_handler($handler);
        try {
            $writer = new StreamLogWriter($path . '/missing.log');
            try {
                $writer->write(new LogRecord(LogLevel::Info, 'Message', new DateTimeImmutable()));
                $this->fail('Expected an open failure.');
            } catch (LogStreamOpenException $exception) {
                $this->assertInstanceOf(ErrorException::class, $exception->getPrevious());
            }
            $previous = set_error_handler($handler);
            restore_error_handler();
            $this->assertSame($handler, $previous);
        } finally {
            restore_error_handler();
            unlink($path);
        }
    }

    public function testWriteFailurePreservesCauseAndLeavesCallerStreamOpen(): void
    {
        [$stream, $peer] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fclose($peer);
        try {
            try {
                new StreamLogWriter($stream)->write(
                    new LogRecord(LogLevel::Info, 'Message', new DateTimeImmutable()),
                );
                $this->fail('Expected a write failure.');
            } catch (LogStreamWriteException $exception) {
                $this->assertInstanceOf(ErrorException::class, $exception->getPrevious());
            }
            $this->assertTrue(is_resource($stream));
        } finally {
            fclose($stream);
        }
    }

    public function testFormattingFailureLeavesDestinationUntouched(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'exa-log-');
        try {
            $failure = new LogFormattingException('Cannot format');
            $formatter = $this->createStub(LogFormatter::class);
            $formatter->method('format')->willThrowException($failure);
            try {
                new StreamLogWriter($path, $formatter)->write(
                    new LogRecord(LogLevel::Info, 'Message', new DateTimeImmutable()),
                );
                $this->fail('Expected formatting failure.');
            } catch (LogFormattingException $exception) {
                $this->assertSame($failure, $exception);
            }
            $this->assertSame('', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }
}
