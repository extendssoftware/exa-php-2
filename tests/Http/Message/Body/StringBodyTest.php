<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Message\Body;

use ExtendsSoftware\ExaPHP\Http\Message\Body\StringBody;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

final class StringBodyTest extends TestCase
{
    public function testEmptyBodyHasNoChunks(): void
    {
        $body = new StringBody();
        self::assertSame(0, $body->size());
        self::assertSame('', $body->content());
        self::assertSame([], iterator_to_array($body->chunks()));
    }

    public function testBinaryContentIsRepeatableAndMeasuredInBytes(): void
    {
        $content = "é\0\xFF";
        $body = new StringBody($content);
        self::assertSame(4, $body->size());
        self::assertSame($content, $body->content());
        self::assertSame([$content], iterator_to_array($body->chunks()));
        self::assertSame([$content], iterator_to_array($body->chunks()));
    }
}
