<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Subscription;

use ExtendsSoftware\ExaPHP\Messaging\Message;

/**
 * Provides subscription definitions without resolving or invoking subscribers.
 */
interface SubscriptionRegistry
{
    /**
     * Finds subscriptions containing the message's exact, case-sensitive type.
     *
     * @param Message $message The message whose type determines matching subscriptions.
     *
     * @return list<Subscription> Matching subscriptions, with each subscriber identifier appearing at most once.
     */
    public function matching(Message $message): array;

    /**
     * Lists all registered subscriptions.
     *
     * @return list<Subscription> Available subscriptions with unique subscriber identifiers.
     */
    public function all(): array;
}
