<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Messaging\Consumption;

use ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception\DeliverySettlementException;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception\InvalidReceivedDeliveryException;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception\MessageReceiveException;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception\UnavailableDeliveryException;
use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class ConsumptionExceptionTest extends TestCase
{
    /** @param class-string<Throwable> $exceptionClass */
    #[DataProvider('exceptions')]
    public function testFailuresImplementTheRootContractAndPreserveTheirCause(string $exceptionClass): void
    {
        $cause = new RuntimeException('Underlying failure.');
        $exception = new $exceptionClass('Delivery operation failed.', 0, $cause);

        self::assertInstanceOf(MessagingException::class, $exception);
        self::assertSame($cause, $exception->getPrevious());
    }

    /** @return iterable<array{class-string<Throwable>}> */
    public static function exceptions(): iterable
    {
        yield [DeliverySettlementException::class];
        yield [InvalidReceivedDeliveryException::class];
        yield [MessageReceiveException::class];
        yield [UnavailableDeliveryException::class];
    }
}
