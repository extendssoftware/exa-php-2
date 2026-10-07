<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Outbox\Processing;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Outbox\OutboxMessage;
use ExtendsSoftware\ExaPHP\Outbox\Processing\ClaimedMessage;
use ExtendsSoftware\ExaPHP\Outbox\Processing\Exception\InvalidClaimException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClaimedMessageTest extends TestCase
{
    public function testPreservesMessageAndOwnershipData(): void
    {
        $createdAt = new DateTimeImmutable('2026-10-06T12:00:00Z');
        $message = new OutboxMessage('message-1', 'example.v1', '{ "value": 1 }', $createdAt);
        $expiresAt = new DateTimeImmutable('2026-10-06T14:01:00.123456+02:00');

        $claim = new ClaimedMessage($message, 2, ' opaque-token ', $expiresAt);

        self::assertSame($message, $claim->message);
        self::assertSame(2, $claim->attempt);
        self::assertSame(' opaque-token ', $claim->token);
        self::assertSame($expiresAt, $claim->expiresAt);
    }

    public function testAcceptsFirstAttemptAndHistoricalExpiryWithoutReadingTheClock(): void
    {
        $time = new DateTimeImmutable('2000-01-01T00:00:00Z');
        $message = new OutboxMessage('message-1', 'example.v1', '{}', $time);

        $claim = new ClaimedMessage($message, 1, 'token-1', $time);

        self::assertSame(1, $claim->attempt);
        self::assertSame($time, $claim->expiresAt);
    }

    #[DataProvider('invalidClaims')]
    public function testRejectsInvalidClaimData(int $attempt, string $token): void
    {
        $time = new DateTimeImmutable('2026-10-06T12:00:00Z');
        $message = new OutboxMessage('message-1', 'example.v1', '{}', $time);

        $this->expectException(InvalidClaimException::class);

        new ClaimedMessage($message, $attempt, $token, $time);
    }

    /** @return iterable<string, array{int, string}> */
    public static function invalidClaims(): iterable
    {
        yield 'zero attempt' => [0, 'token-1'];
        yield 'negative attempt' => [-1, 'token-1'];
        yield 'empty ownership token' => [1, ''];
    }
}
