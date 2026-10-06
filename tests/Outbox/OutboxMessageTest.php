<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Outbox;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Outbox\Exception\InvalidOutboxMessageException;
use ExtendsSoftware\ExaPHP\Outbox\OutboxMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_repeat;

final class OutboxMessageTest extends TestCase
{
    public function testPreservesCallerSuppliedEnvelopeData(): void
    {
        $createdAt = new DateTimeImmutable('2026-10-06T12:34:56.123456+02:00');
        $payload = '{ "articleId": "article-1", "tags": ["news"], "note": null }';
        $message = new OutboxMessage('message-1', 'article.announcement.requested.v1', $payload, $createdAt);

        self::assertSame('message-1', $message->id);
        self::assertSame('article.announcement.requested.v1', $message->type);
        self::assertSame($payload, $message->payload);
        self::assertSame($createdAt, $message->createdAt);
    }

    #[DataProvider('validPayloads')]
    public function testAcceptsPortableJsonValues(string $payload): void
    {
        $message = new OutboxMessage('message-1', 'example.v1', $payload, new DateTimeImmutable('2026-10-06'));

        self::assertSame($payload, $message->payload);
    }

    /** @return iterable<string, array{string}> */
    public static function validPayloads(): iterable
    {
        yield 'object' => ['{}'];
        yield 'list' => ['[1, true, null, {"title":"Hello"}]'];
        yield 'string' => ['"Hello"'];
        yield 'number' => ['42'];
        yield 'boolean' => ['false'];
        yield 'null' => ['null'];
    }

    #[DataProvider('invalidMessages')]
    public function testRejectsInvalidEnvelopeData(string $id, string $type, string $payload): void
    {
        $this->expectException(InvalidOutboxMessageException::class);

        new OutboxMessage($id, $type, $payload, new DateTimeImmutable('2026-10-06'));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidMessages(): iterable
    {
        yield 'empty identifier' => ['', 'example.v1', '{}'];
        yield 'empty type' => ['message-1', '', '{}'];
        yield 'empty payload' => ['message-1', 'example.v1', ''];
        yield 'malformed JSON' => ['message-1', 'example.v1', '{'];
        yield 'invalid UTF-8' => ['message-1', 'example.v1', "\"\xFF\""];
        yield 'excessive depth' => ['message-1', 'example.v1', str_repeat('[', 512) . '0' . str_repeat(']', 512)];
    }
}
