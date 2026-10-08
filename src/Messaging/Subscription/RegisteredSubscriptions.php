<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Subscription;

use ExtendsSoftware\ExaPHP\Messaging\Message;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\Exception\DuplicateSubscriptionException;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\Exception\InvalidSubscriptionRegistrationException;
use Override;

use function array_is_list;
use function in_array;

/**
 * Holds immutable subscription registrations and matches message types in registration order.
 */
final readonly class RegisteredSubscriptions implements SubscriptionRegistry
{
    /**
     * Registers subscriptions without resolving or invoking subscribers.
     *
     * Empty registration lists are valid. Subscriber identifiers must be unique even when their message types differ.
     *
     * @param list<Subscription> $subscriptions The subscriptions in listing and matching order.
     *
     * @throws InvalidSubscriptionRegistrationException When registrations are not a list of subscriptions.
     * @throws DuplicateSubscriptionException When a subscriber identifier is registered more than once.
     */
    public function __construct(private array $subscriptions = [])
    {
        if (!array_is_list($subscriptions)) {
            throw new InvalidSubscriptionRegistrationException('Subscription registrations must be a list.');
        }
        $identifiers = [];
        foreach ($subscriptions as $subscription) {
            if (!$subscription instanceof Subscription) {
                throw new InvalidSubscriptionRegistrationException('Registrations must be Subscription instances.');
            }
            if (in_array($subscription->subscriberId, $identifiers, true)) {
                throw new DuplicateSubscriptionException(
                    'Subscriber "' . $subscription->subscriberId . '" is already registered.',
                );
            }
            $identifiers[] = $subscription->subscriberId;
        }
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function matching(Message $message): array
    {
        $matching = [];
        foreach ($this->subscriptions as $subscription) {
            if (in_array($message->type, $subscription->messageTypes, true)) {
                $matching[] = $subscription;
            }
        }

        return $matching;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function all(): array
    {
        return $this->subscriptions;
    }
}
