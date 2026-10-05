<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing;

use ExtendsSoftware\ExaPHP\Processing\Exception\InvalidViolationException;
use ExtendsSoftware\ExaPHP\Processing\Violation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ViolationTest extends TestCase
{
    public function testPreservesMetadata(): void
    {
        $violation = new Violation('too_short', 'Too short', ['articles', 0, 'title'], ['minimum' => 3]);
        $this->assertSame('too_short', $violation->code());
        $this->assertSame('Too short', $violation->message());
        $this->assertSame(['articles', 0, 'title'], $violation->path());
        $this->assertSame(['minimum' => 3], $violation->parameters());
    }

    public function testDefaultsToRootWithoutParameters(): void
    {
        $violation = new Violation('invalid', 'Invalid');
        $this->assertSame([], $violation->path());
        $this->assertSame([], $violation->parameters());
    }

    /**
     * @param array<array-key, mixed> $path
     * @param array<array-key, mixed> $parameters
     */
    #[DataProvider('invalidMetadata')]
    public function testRejectsInvalidMetadata(string $code, array $path, array $parameters): void
    {
        $this->expectException(InvalidViolationException::class);
        new Violation($code, 'Invalid', $path, $parameters);
    }

    /**
     * @return iterable<array{string, array<array-key, mixed>, array<array-key, mixed>}>
     */
    public static function invalidMetadata(): iterable
    {
        yield ['', [], []];
        yield ['invalid', ['field' => 'name'], []];
        yield ['invalid', [false], []];
        yield ['invalid', [], [0 => 'value']];
    }
}
