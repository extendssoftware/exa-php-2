<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Outbox;

use ExtendsSoftware\ExaPHP\Outbox\Exception\InvalidOutboxMessageException;
use ExtendsSoftware\ExaPHP\Outbox\Exception\OutboxWriteException;
use ExtendsSoftware\ExaPHP\Outbox\OutboxException;
use ExtendsSoftware\ExaPHP\Outbox\Processing\Exception\InvalidClaimException;
use ExtendsSoftware\ExaPHP\Outbox\Processing\Exception\LostClaimException;
use ExtendsSoftware\ExaPHP\Outbox\Processing\Exception\OutboxStoreException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OutboxExceptionTest extends TestCase
{
    public function testWriteFailuresPreserveTheirCauseAndImplementTheRootContract(): void
    {
        $cause = new RuntimeException('Connection unavailable.');
        $exception = new OutboxWriteException('Could not append message-1 to the outbox.', 0, $cause);

        self::assertInstanceOf(OutboxException::class, $exception);
        self::assertSame($cause, $exception->getPrevious());
    }

    public function testStoreFailuresPreserveTheirCauseAndImplementTheRootContract(): void
    {
        $cause = new RuntimeException('Connection unavailable.');
        $exception = new OutboxStoreException('Could not complete message-1.', 0, $cause);

        self::assertInstanceOf(OutboxException::class, $exception);
        self::assertSame($cause, $exception->getPrevious());
    }

    public function testClaimFailuresImplementTheRootContract(): void
    {
        self::assertInstanceOf(OutboxException::class, new InvalidClaimException('Invalid expiry.'));
        self::assertInstanceOf(OutboxException::class, new LostClaimException('Claim expired.'));
    }

    public function testInvalidMessagesImplementTheRootContract(): void
    {
        self::assertInstanceOf(OutboxException::class, new InvalidOutboxMessageException('Invalid payload.'));
    }
}
