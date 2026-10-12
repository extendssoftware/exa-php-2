<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cli\Output;

use ExtendsSoftware\ExaPHP\Cli\Output\Exception\InvalidOutputStreamException;
use ExtendsSoftware\ExaPHP\Cli\Output\Exception\OutputWriteException;
use ExtendsSoftware\ExaPHP\Cli\Output\StreamOutput;
use ExtendsSoftware\ExaPHP\Tests\Cli\Output\Fixture\PartialOutputStream;
use PHPUnit\Framework\TestCase;

use function fclose;
use function fopen;
use function fwrite;
use function is_resource;
use function rewind;
use function stream_get_contents;
use function stream_get_meta_data;
use function stream_wrapper_register;
use function stream_wrapper_unregister;

final class StreamOutputIntegrationTest extends TestCase
{
    public function testWritesExactBytesToSeparateChannelsWithoutRewindingOrClosing(): void
    {
        $stdout = fopen('php://memory', 'w+b');
        $stderr = fopen('php://memory', 'w+b');
        try {
            fwrite($stdout, 'prefix:');
            $output = new StreamOutput($stdout, $stderr);
            $output->write('one');
            $output->write('');
            $output->write("\0two\n");
            $output->writeError('error');
            rewind($stdout);
            rewind($stderr);
            self::assertSame("prefix:one\0two\n", stream_get_contents($stdout));
            self::assertSame('error', stream_get_contents($stderr));
            unset($output);
            self::assertTrue(is_resource($stdout));
            self::assertTrue(is_resource($stderr));
        } finally {
            fclose($stdout);
            fclose($stderr);
        }
    }

    public function testCompletesPartialWritesAndRejectsZeroProgress(): void
    {
        stream_wrapper_register('exaoutput', PartialOutputStream::class);
        $stream = fopen('exaoutput://test', 'w');
        try {
            $output = new StreamOutput($stream, $stream);
            $output->write('abcdef');
            $wrapper = stream_get_meta_data($stream)['wrapper_data'];
            self::assertSame('abcdef', $wrapper->bytes);
            try {
                $output->writeError('ab!stop');
                self::fail('Expected zero-progress failure.');
            } catch (OutputWriteException $exception) {
                self::assertStringContainsString('stderr', $exception->getMessage());
                self::assertSame('abcdefab', $wrapper->bytes);
            }
        } finally {
            fclose($stream);
            stream_wrapper_unregister('exaoutput');
        }
    }

    public function testRejectsClosedStreamEvenForEmptyWrites(): void
    {
        $stream = fopen('php://memory', 'w+b');
        $output = new StreamOutput($stream, $stream);
        fclose($stream);
        $this->expectException(InvalidOutputStreamException::class);
        $output->write('');
    }

    public function testRejectsReadOnlyStderr(): void
    {
        $stdout = fopen('php://memory', 'w+b');
        $stderr = fopen('php://memory', 'r');
        try {
            $this->expectException(InvalidOutputStreamException::class);
            new StreamOutput($stdout, $stderr);
        } finally {
            fclose($stdout);
            fclose($stderr);
        }
    }

    public function testRejectsNonStreamDestinations(): void
    {
        $this->expectException(InvalidOutputStreamException::class);
        new StreamOutput('php://stdout', 'php://stderr');
    }
}
