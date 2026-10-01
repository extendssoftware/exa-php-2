<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cqrs;

use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;
use ExtendsSoftware\ExaPHP\Cqrs\Exception\DispatchMetadataNotFoundException;
use ExtendsSoftware\ExaPHP\Cqrs\Exception\InvalidDispatchMetadataException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class DispatchContextTest extends TestCase
{
    public function testAddingAndReplacingMetadataLeavesPreviousContextsUnchanged(): void
    {
        $empty = new DispatchContext();
        $first = new stdClass();
        $second = new stdClass();
        $original = $empty->with($first);
        $replacement = $original->with($second);

        self::assertFalse($empty->has(stdClass::class));
        self::assertTrue($original->has(stdClass::class));
        self::assertSame($first, $original->get(stdClass::class));
        self::assertSame($second, $replacement->get(stdClass::class));
    }

    public function testMissingMetadataThrows(): void
    {
        $this->expectException(DispatchMetadataNotFoundException::class);
        new DispatchContext()->get(stdClass::class);
    }

    public function testInputKeysAreIgnored(): void
    {
        $metadata = new stdClass();
        self::assertSame($metadata, new DispatchContext(['actor' => $metadata])->get(stdClass::class));
    }

    #[DataProvider('invalidMetadata')]
    public function testRejectsInvalidMetadata(array $metadata): void
    {
        $this->expectException(InvalidDispatchMetadataException::class);
        new DispatchContext($metadata);
    }

    /**
     * @return iterable<array{array<mixed>}>
     */
    public static function invalidMetadata(): iterable
    {
        yield [[null]];
        yield [['actor']];
        yield [[new stdClass(), new stdClass()]];
    }
}
