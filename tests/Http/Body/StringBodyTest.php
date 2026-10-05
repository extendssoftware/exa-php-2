<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Body;

use ExtendsSoftware\ExaPHP\Http\Body\StringBody;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

final class StringBodyTest extends TestCase
{
    public function testEmptyBodyHasNoChunks(): void
    {
        $body = new StringBody();
        $this->assertSame(0, $body->size());
        $this->assertSame('', $body->content());
        $this->assertSame([], iterator_to_array($body->chunks()));
    }

    public function testBinaryContentIsRepeatableAndMeasuredInBytes(): void
    {
        $content = "é\0\xFF";
        $body = new StringBody($content);
        $this->assertSame(4, $body->size());
        $this->assertSame($content, $body->content());
        $this->assertSame([$content], iterator_to_array($body->chunks()));
        $this->assertSame([$content], iterator_to_array($body->chunks()));
    }
}
