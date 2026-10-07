<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Outbox\Retry;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Outbox\Delivery\Exception\MessageDeliveryException;
use ExtendsSoftware\ExaPHP\Outbox\OutboxMessage;
use ExtendsSoftware\ExaPHP\Outbox\Processing\ClaimedMessage;
use ExtendsSoftware\ExaPHP\Outbox\Retry\Exception\InvalidRetryPolicyException;
use ExtendsSoftware\ExaPHP\Outbox\Retry\FixedDelayRetryPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FixedDelayRetryPolicyTest extends TestCase
{
    #[DataProvider('retryDecisions')]
    public function testLimitsTotalAttempts(int $delay, int $limit, int $attempt, ?int $expected): void
    {
        $createdAt = new DateTimeImmutable('2026-10-07T12:00:00Z');
        $message = new OutboxMessage('message-1', 'example.v1', '{}', $createdAt);
        $claim = new ClaimedMessage($message, $attempt, 'token-1', $createdAt->modify('+30 seconds'));
        $policy = new FixedDelayRetryPolicy($delay, $limit);

        self::assertSame($expected, $policy->delay($claim, new MessageDeliveryException('Timed out.')));
    }

    /** @return iterable<string, array{int, int, int, int|null}> */
    public static function retryDecisions(): iterable
    {
        yield 'initial attempt' => [60, 3, 1, 60];
        yield 'last retry' => [60, 3, 2, 60];
        yield 'limit reached' => [60, 3, 3, null];
        yield 'limit exceeded' => [60, 3, 4, null];
        yield 'no retries' => [60, 1, 1, null];
        yield 'immediate retry' => [0, 3, 1, 0];
    }

    #[DataProvider('invalidConfiguration')]
    public function testRejectsInvalidConfiguration(int $delay, int $limit): void
    {
        $this->expectException(InvalidRetryPolicyException::class);

        new FixedDelayRetryPolicy($delay, $limit);
    }

    /** @return iterable<string, array{int, int}> */
    public static function invalidConfiguration(): iterable
    {
        yield 'negative delay' => [-1, 3];
        yield 'zero limit' => [60, 0];
        yield 'negative limit' => [60, -1];
    }
}
