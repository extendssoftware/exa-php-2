<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Event;

use Throwable;

/**
 * Dispatches events to interested listeners without returning a result.
 */
interface EventDispatcher
{
    /**
     * Dispatches an event to zero or more listeners.
     *
     * An event without listeners is valid. Listener exceptions and errors propagate unchanged and prevent
     * subsequent listeners from being invoked for this dispatch.
     *
     * @param Event $event The event to dispatch.
     *
     * @return void
     *
     * @throws EventException When the event cannot be dispatched.
     * @throws Throwable When a listener throws an exception or error, propagated unchanged.
     */
    public function dispatch(Event $event): void;
}
