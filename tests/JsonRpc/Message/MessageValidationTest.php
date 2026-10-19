<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\JsonRpc\Message;

use Closure;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\ErrorDetails;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\ErrorResponse;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\Exception\InvalidMessageException;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\Notification;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\Request;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\ResultResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use const INF;
use const NAN;

final class MessageValidationTest extends TestCase
{
    /** @param Closure(): object $create */
    #[DataProvider('invalidEnvelopes')]
    public function testRejectsInvalidEnvelopeFields(Closure $create): void
    {
        $this->expectException(InvalidMessageException::class);
        $create();
    }

    /** @return iterable<string, array{Closure(): object}> */
    public static function invalidEnvelopes(): iterable
    {
        foreach ([INF, -INF, NAN, "\xff"] as $index => $id) {
            yield 'request identifier ' . $index => [static fn(): object => new Request($id, 'example')];
            yield 'result identifier ' . $index => [static fn(): object => new ResultResponse($id, null)];
            yield 'error identifier ' . $index => [
                static fn(): object => new ErrorResponse($id, new ErrorDetails(1001, 'Public error')),
            ];
        }
        yield 'request argument structure' => [static fn(): object => new Request(1, 'example', ['name' => 'Ada'])];
        yield 'notification argument structure' => [
            static fn(): object => new Notification('example', ['name' => 'Ada']),
        ];
        yield 'request method encoding' => [static fn(): object => new Request(1, "\xff")];
        yield 'notification method encoding' => [static fn(): object => new Notification("\xff")];
    }
}
