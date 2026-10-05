<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http;

use ExtendsSoftware\ExaPHP\Http\Exception\InvalidRequestAttributesException;
use ExtendsSoftware\ExaPHP\Http\Exception\RequestAttributeNotFoundException;
use ExtendsSoftware\ExaPHP\Http\Headers;
use ExtendsSoftware\ExaPHP\Http\Method;
use ExtendsSoftware\ExaPHP\Http\ProtocolVersion;
use ExtendsSoftware\ExaPHP\Http\Request;
use ExtendsSoftware\ExaPHP\Http\RequestAttributes;
use ExtendsSoftware\ExaPHP\Http\Uri;
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
