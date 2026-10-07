<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Messaging\Consumption\Retry;

use DateTimeImmutable;
use RuntimeException;
use ExtendsSoftware\ExaPHP\Messaging\Message;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\ReceivedDelivery;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry\Exception\InvalidRetryPolicyException;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry\FixedDelayRetryPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FixedDelayRetryPolicyTest extends TestCase
{
    #[DataProvider('retryDecisions')]
    public function testLimitsTotalAttempts(int $delay, int $limit, int $attempt, ?int $expected): void
    {
        $createdAt = new DateTimeImmutable('2026-10-07T12:00:00Z');
        $message = new Message('message-1', 'example.v1', '{}', $createdAt);
        $claim = new ReceivedDelivery($message, 'subscriber', 'receipt', $attempt);
        $policy = new FixedDelayRetryPolicy($delay, $limit);

        self::assertSame($expected, $policy->delay($claim, new RuntimeException('Timed out.')));
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
