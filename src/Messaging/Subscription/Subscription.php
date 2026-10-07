<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Subscription;

use ExtendsSoftware\ExaPHP\Messaging\Subscription\Exception\InvalidSubscriptionException;

use function array_is_list;
use function in_array;
use function is_string;

/**
 * Defines a subscriber's stable identity and subscribed message types.
 */
final readonly class Subscription
{
    /**
     * Creates a subscription without resolving or invoking a subscriber.
     *
     * Identifiers and message types are case-sensitive and preserved without normalization. Types must be unique;
     * each names an exact message type, with no wildcard interpretation.
     *
     * @param non-empty-string $subscriberId The stable subscriber identifier used for delivery tracking.
     * @param non-empty-list<non-empty-string> $messageTypes The subscribed message types in declaration order.
     *
     * @throws InvalidSubscriptionException When the identifier or type list is invalid or a type is repeated.
     */
    public function __construct(public string $subscriberId, public array $messageTypes)
    {
        if ($subscriberId === '') {
            throw new InvalidSubscriptionException('Subscriber identifiers must not be empty.');
        }
        if ($messageTypes === [] || !array_is_list($messageTypes)) {
            throw new InvalidSubscriptionException('Subscribed message types must be a non-empty list.');
        }
        $seen = [];
        foreach ($messageTypes as $type) {
            if (!is_string($type) || $type === '') {
                throw new InvalidSubscriptionException('Subscribed message types must be non-empty strings.');
            }
            if (in_array($type, $seen, true)) {
                throw new InvalidSubscriptionException('Subscribed message types must not repeat.');
            }
            $seen[] = $type;
        }
    }
}
