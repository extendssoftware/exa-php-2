<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\JsonRpc\Message;

use Error;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\ErrorDetails;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\ErrorResponse;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\MessageEncoder;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\ResultResponse;
use Generator;
use JsonSerializable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

use function json_decode;

use const INF;
use const JSON_THROW_ON_ERROR;

final class MessageEncoderTest extends TestCase
{
    public function testAddsProtocolFieldsWithoutExposingEnvelopeClassProperties(): void
    {
        self::assertSame(
            '{"jsonrpc":"2.0","result":null,"id":0.0}',
            new MessageEncoder()->encode(new ResultResponse(0.0, null)),
        );
    }

    public function testOmitsAbsentErrorDataAndPreservesExplicitFalseData(): void
    {
        $encoder = new MessageEncoder();

        self::assertSame(
            '{"jsonrpc":"2.0","error":{"code":1001,"message":"Public error"},"id":null}',
            $encoder->encode(new ErrorResponse(null, new ErrorDetails(1001, 'Public error'))),
        );
        self::assertSame(
            '{"jsonrpc":"2.0","error":{"code":1001,"message":"Public error","data":false},"id":1}',
            $encoder->encode(new ErrorResponse(1, new ErrorDetails(1001, 'Public error', false))),
        );
    }

    public function testEmptyResponseSourceProducesNoDocument(): void
    {
        self::assertNull(new MessageEncoder()->encodeBatch([]));
    }

    public function testConsumesBatchLazilyAndSerializesEachResponseOnlyOnce(): void
    {
        $events = (object) ['entries' => []];
        $result = new class ($events) implements JsonSerializable {
            public function __construct(private stdClass $events) {}

            public function jsonSerialize(): mixed
            {
                $this->events->entries[] = 'serialize';

                return 'ok';
            }
        };
        $source = (static function () use ($result, $events): Generator {
            $events->entries[] = 'first';
            yield new ResultResponse(1, $result);
            $events->entries[] = 'second';
            yield new ResultResponse(2, INF);
        })();

        $json = new MessageEncoder()->encodeBatch($source);

        self::assertSame(['first', 'serialize', 'second'], $events->entries);
        self::assertIsString($json);
        $responses = json_decode($json, flags: JSON_THROW_ON_ERROR);
        self::assertSame('ok', $responses[0]->result);
        self::assertSame(-32603, $responses[1]->error->code);
        self::assertSame(2, $responses[1]->id);
    }

    public function testDoesNotRetryFailingApplicationSerializer(): void
    {
        $result = new class implements JsonSerializable {
            public int $calls = 0;

            public function jsonSerialize(): mixed
            {
                ++$this->calls;
                throw new Error('secret');
            }
        };

        self::assertSame(
            '{"jsonrpc":"2.0","error":{"code":-32603,"message":"Internal error"},"id":"request"}',
            new MessageEncoder()->encode(new ResultResponse('request', $result)),
        );
        self::assertSame(1, $result->calls);
    }

    public function testPropagatesSourceFailuresInsteadOfReportingAMethodFailure(): void
    {
        $failure = new RuntimeException('source failed');
        $source = (static function () use ($failure): Generator {
            yield new ResultResponse(1, 'ok');
            throw $failure;
        })();

        $this->expectExceptionObject($failure);
        new MessageEncoder()->encodeBatch($source);
    }
}
