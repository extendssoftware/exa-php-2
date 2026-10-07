<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Messaging;

use ExtendsSoftware\ExaPHP\Messaging\Exception\InvalidMessageException;
use ExtendsSoftware\ExaPHP\Messaging\Exception\MessagePublishException;
use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MessagingExceptionTest extends TestCase
{
    public function testPublishFailuresPreserveTheirCauseAndImplementTheRootContract(): void
    {
        $cause = new RuntimeException('Connection unavailable.');
        $exception = new MessagePublishException('Unable to publish message-1.', 0, $cause);

        self::assertInstanceOf(MessagingException::class, $exception);
        self::assertSame($cause, $exception->getPrevious());
    }

    public function testInvalidMessagesImplementTheRootContract(): void
    {
        self::assertInstanceOf(MessagingException::class, new InvalidMessageException('Invalid payload.'));
    }
}
