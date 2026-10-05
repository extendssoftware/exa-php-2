<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Message\Body;

use ExtendsSoftware\ExaPHP\Http\Message\Body\StreamBody;
use ExtendsSoftware\ExaPHP\Http\Message\Exception\BodyReadException;
use ExtendsSoftware\ExaPHP\Http\Message\Exception\InvalidBodyStreamException;
use PHPUnit\Framework\TestCase;

use function fclose;
use function fopen;
use function fwrite;
use function fread;
use function implode;
use function is_resource;
use function iterator_to_array;
use function rewind;
use function str_repeat;

final class StreamBodyIntegrationTest extends TestCase
{
    public function testConsumesChunksFromCurrentPositionWithoutClosingStream(): void
    {
        $stream = fopen('php://memory', 'w+b');
        try {
            fwrite($stream, 'skip' . str_repeat('x', 20000));
            rewind($stream);
            fread($stream, 4);
            $body = new StreamBody($stream);
            $this->assertNull($body->size());
            $chunks = iterator_to_array($body->chunks());
            $this->assertCount(3, $chunks);
            $this->assertSame(str_repeat('x', 20000), implode('', $chunks));
            $this->assertTrue(is_resource($stream));
            $this->expectException(BodyReadException::class);
            iterator_to_array($body->chunks());
        } finally {
            fclose($stream);
        }
    }

    public function testRejectsConcurrentAndAbandonedIteration(): void
    {
        $stream = fopen('php://memory', 'w+b');
        try {
            fwrite($stream, 'body');
            rewind($stream);
            $body = new StreamBody($stream);
            $first = $body->chunks();
            $this->assertSame('body', $first->current());
            $this->expectException(BodyReadException::class);
            iterator_to_array($body->chunks());
        } finally {
            fclose($stream);
        }
    }

    public function testClosedStreamFailsDuringConsumption(): void
    {
        $stream = fopen('php://memory', 'r');
        $body = new StreamBody($stream);
        fclose($stream);
        $this->expectException(BodyReadException::class);
        iterator_to_array($body->chunks());
    }

    public function testRejectsInvalidStream(): void
    {
        $this->expectException(InvalidBodyStreamException::class);
        new StreamBody('php://input');
    }

    public function testEmptyStreamProducesNoChunks(): void
    {
        $stream = fopen('php://memory', 'r');
        try {
            $this->assertSame([], iterator_to_array(new StreamBody($stream)->chunks()));
        } finally {
            fclose($stream);
        }
    }
}
