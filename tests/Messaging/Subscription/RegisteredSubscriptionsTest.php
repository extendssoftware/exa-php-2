<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Messaging\Subscription;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Messaging\Message;
use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\Exception\DuplicateSubscriptionException;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\Exception\InvalidSubscriptionRegistrationException;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\RegisteredSubscriptions;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\Subscription;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class RegisteredSubscriptionsTest extends TestCase
{
    public function testMatchesSharedAndMultipleTypesInRegistrationOrder(): void
    {
        $first = new Subscription('search-index', ['article.created.v1', 'article.updated.v1']);
        $unrelated = new Subscription('billing', ['invoice.created.v1']);
        $last = new Subscription('notifications', ['article.created.v1']);
        $registry = new RegisteredSubscriptions([$first, $unrelated, $last]);

        self::assertSame([$first, $unrelated, $last], $registry->all());
        self::assertSame([$first, $last], $registry->matching($this->message('article.created.v1')));
        self::assertSame([$first], $registry->matching($this->message('article.updated.v1')));
        self::assertSame([], $registry->matching($this->message('unknown.v1')));
    }

    public function testMatchesTypesExactlyWithoutWildcardOrCaseNormalization(): void
    {
        $wildcard = new Subscription('wildcard', ['article.*']);
        $upper = new Subscription('Subscriber', ['Article.Created.v1']);
        $lower = new Subscription('subscriber', ['article.created.v1']);
        $numeric = new Subscription('0', ['0']);
        $registry = new RegisteredSubscriptions([$wildcard, $upper, $lower, $numeric]);

        self::assertSame([$wildcard], $registry->matching($this->message('article.*')));
        self::assertSame([$upper], $registry->matching($this->message('Article.Created.v1')));
        self::assertSame([$lower], $registry->matching($this->message('article.created.v1')));
        self::assertSame([$numeric], $registry->matching($this->message('0')));
        self::assertSame([], $registry->matching($this->message(' article.created.v1')));
    }

    public function testAcceptsAnEmptyRegistry(): void
    {
        $registry = new RegisteredSubscriptions();

        self::assertSame([], $registry->all());
        self::assertSame([], $registry->matching($this->message('example.v1')));
    }

    /** @param array<array-key, mixed> $subscriptions */
    #[DataProvider('invalidRegistrations')]
    public function testRejectsMalformedRegistrations(array $subscriptions): void
    {
        try {
            new RegisteredSubscriptions($subscriptions);
            self::fail('Expected invalid registration.');
        } catch (InvalidSubscriptionRegistrationException $exception) {
            self::assertInstanceOf(MessagingException::class, $exception);
        }
    }

    /** @return iterable<string, array{array<array-key, mixed>}> */
    public static function invalidRegistrations(): iterable
    {
        yield 'keyed registrations' => [['subscriber' => new Subscription('subscriber', ['example.v1'])]];
        yield 'sparse registrations' => [[1 => new Subscription('subscriber', ['example.v1'])]];
        yield 'string registration' => [['subscriber']];
        yield 'unrelated object' => [[new stdClass()]];
        yield 'null registration' => [[null]];
    }

    /** @param list<Subscription> $subscriptions */
    #[DataProvider('duplicateRegistrations')]
    public function testRejectsDuplicateSubscriberIdentifiers(array $subscriptions): void
    {
        try {
            new RegisteredSubscriptions($subscriptions);
            self::fail('Expected duplicate registration.');
        } catch (DuplicateSubscriptionException $exception) {
            self::assertInstanceOf(MessagingException::class, $exception);
            self::assertStringContainsString('subscriber', $exception->getMessage());
        }
    }

    /** @return iterable<string, array{list<Subscription>}> */
    public static function duplicateRegistrations(): iterable
    {
        $subscription = new Subscription('subscriber', ['example.v1']);
        yield 'same object' => [[$subscription, $subscription]];
        yield 'same definition' => [[$subscription, new Subscription('subscriber', ['example.v1'])]];
        yield 'different types' => [[$subscription, new Subscription('subscriber', ['other.v1'])]];
    }

    private function message(string $type): Message
    {
        return new Message('message-1', $type, '{}', new DateTimeImmutable('2026-10-07T12:00:00Z'));
    }
}
