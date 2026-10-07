<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Identity;

use ExtendsSoftware\ExaPHP\Identity\Actor;
use ExtendsSoftware\ExaPHP\Identity\Exception\InvalidActorException;
use ExtendsSoftware\ExaPHP\Identity\IdentityException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ActorTest extends TestCase
{
    #[DataProvider('actors')]
    public function testPreservesApplicationDefinedIdentity(string $id, string $kind): void
    {
        $actor = new Actor($id, $kind);

        self::assertSame($id, $actor->id);
        self::assertSame($kind, $actor->kind);
    }

    /** @return iterable<string, array{string, string}> */
    public static function actors(): iterable
    {
        yield 'user' => ['42', 'user'];
        yield 'service' => ['42', 'service'];
        yield 'system' => ['scheduler', 'system'];
        yield 'custom kind' => ['device-1', 'device'];
        yield 'zero strings' => ['0', '0'];
        yield 'no normalization' => [' Principal-1 ', ' CustomKind '];
    }

    #[DataProvider('invalidActors')]
    public function testRejectsEmptyIdentityValues(string $id, string $kind): void
    {
        try {
            new Actor($id, $kind);
            self::fail('Expected invalid actor.');
        } catch (InvalidActorException $exception) {
            self::assertInstanceOf(IdentityException::class, $exception);
            self::assertInstanceOf(InvalidArgumentException::class, $exception);
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidActors(): iterable
    {
        yield 'empty identifier' => ['', 'user'];
        yield 'empty kind' => ['42', ''];
        yield 'both empty' => ['', ''];
    }
}
