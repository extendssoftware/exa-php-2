<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Messaging\Subscription;

use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\Exception\InvalidSubscriptionException;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\Subscription;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SubscriptionTest extends TestCase
{
    public function testPreservesSubscriberIdentityAndTypeOrderWithoutNormalization(): void
    {
        $types = ['article.created.v1', 'Article.Created.v1', '0', ' article.updated.v1 '];
        $subscription = new Subscription(' subscriber-1 ', $types);

        self::assertSame(' subscriber-1 ', $subscription->subscriberId);
        self::assertSame($types, $subscription->messageTypes);
    }

    /** @param array<array-key, mixed> $types */
    #[DataProvider('invalidSubscriptions')]
    public function testRejectsInvalidDefinitions(string $id, array $types): void
    {
        try {
            new Subscription($id, $types);
            self::fail('Expected invalid subscription.');
        } catch (InvalidSubscriptionException $exception) {
            self::assertInstanceOf(MessagingException::class, $exception);
        }
    }

    /** @return iterable<string, array{string, array<array-key, mixed>}> */
    public static function invalidSubscriptions(): iterable
    {
        yield 'empty identifier' => ['', ['example.v1']];
        yield 'empty types' => ['subscriber', []];
        yield 'keyed types' => ['subscriber', ['type' => 'example.v1']];
        yield 'sparse types' => ['subscriber', [1 => 'example.v1']];
        yield 'empty type' => ['subscriber', ['']];
        yield 'integer type' => ['subscriber', [1]];
        yield 'null type' => ['subscriber', [null]];
        yield 'duplicate types' => ['subscriber', ['example.v1', 'example.v1']];
    }
}
