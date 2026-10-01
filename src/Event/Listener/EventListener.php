<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Event\Listener;

use ExtendsSoftware\ExaPHP\Event\Event;
use Throwable;

/**
 * Reacts to an event without returning a result.
 *
 * @template TEvent of Event
 */
interface EventListener
{
    /**
     * Handles an event that has occurred.
     *
     * @param TEvent $event The event to handle.
     *
     * @return void
     *
     * @throws Throwable When event handling raises a domain exception or another exception or error.
     */
    public function handle(Event $event): void;
}
