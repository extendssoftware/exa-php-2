<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Outbox;

use ExtendsSoftware\ExaPHP\Outbox\Exception\InvalidOutboxMessageException;
use ExtendsSoftware\ExaPHP\Outbox\Exception\OutboxWriteException;
use ExtendsSoftware\ExaPHP\Outbox\OutboxException;
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

    public function testInvalidMessagesImplementTheRootContract(): void
    {
        self::assertInstanceOf(OutboxException::class, new InvalidOutboxMessageException('Invalid payload.'));
    }
}
