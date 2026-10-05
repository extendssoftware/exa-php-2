<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Message;

use ExtendsSoftware\ExaPHP\Http\Message\Exception\InvalidRequestAttributesException;
use ExtendsSoftware\ExaPHP\Http\Message\Exception\RequestAttributeNotFoundException;
use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\ProtocolVersion;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\RequestAttributes;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use PHPUnit\Framework\TestCase;
use stdClass;

final class RequestAttributesTest extends TestCase
{
    public function testWithAndWithoutAreImmutableAndPreserveIdentity(): void
    {
        $first = new stdClass();
        $second = new stdClass();
        $original = new RequestAttributes([$first]);
        $updated = $original->with($second);
        $removed = $updated->without(stdClass::class);
        $this->assertSame($first, $original->get(stdClass::class));
        $this->assertSame($second, $updated->get(stdClass::class));
        $this->assertFalse($removed->has(stdClass::class));
        $this->assertTrue($updated->has(stdClass::class));
    }

    public function testAbsentAttributeThrows(): void
    {
        $this->expectException(RequestAttributeNotFoundException::class);
        new RequestAttributes()->get(stdClass::class);
    }

    public function testDuplicateConcreteClassIsRejected(): void
    {
        $this->expectException(InvalidRequestAttributesException::class);
        new RequestAttributes([new stdClass(), new stdClass()]);
    }

    public function testNonObjectIsRejected(): void
    {
        $this->expectException(InvalidRequestAttributesException::class);
        new RequestAttributes(['invalid']);
    }

    public function testEveryRequestReplacementRetainsAttributes(): void
    {
        $metadata = new stdClass();
        $original = new Request(Method::Get, new Uri('/'));
        $request = $original->withAttribute($metadata);
        $this->assertFalse($original->attributes->has(stdClass::class));
        $copies = [
            $request->withMethod(Method::Post),
            $request->withUri(new Uri('/another')),
            $request->withHeaders(new Headers(['X-Test' => 'value'])),
            $request->withBody($original->body),
            $request->withProtocolVersion(ProtocolVersion::Http2),
        ];
        foreach ($copies as $copy) {
            $this->assertSame($metadata, $copy->attributes->get(stdClass::class));
            $this->assertSame($request->attributes, $copy->attributes);
        }
        $this->assertFalse($request->withAttributes(new RequestAttributes())->attributes->has(stdClass::class));
    }
}
