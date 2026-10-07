<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Subscription;

use ExtendsSoftware\ExaPHP\Messaging\Subscription\Exception\SubscriberResolutionException;

/**
 * Resolves subscribers by their stable identifiers without processing messages.
 */
interface SubscriberResolver
{
    /**
     * Returns the subscriber associated with an exact, case-sensitive identifier.
     *
     * @param non-empty-string $subscriberId The stable subscriber identifier.
     *
     * @return MessageSubscriber The resolved subscriber.
     *
     * @throws SubscriberResolutionException When the identifier cannot be resolved to a subscriber.
     */
    public function resolve(string $subscriberId): MessageSubscriber;
}
