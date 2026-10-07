<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Subscription;

use ExtendsSoftware\ExaPHP\Messaging\Message;
use Throwable;

/**
 * Performs application processing for a delivered message.
 */
interface MessageSubscriber
{
    /**
     * Handles one message and returns when processing succeeds.
     *
     * Failures must propagate to the caller. Successful return does not acknowledge the delivery; acknowledgement
     * and retry decisions belong to the caller. Messages may be delivered repeatedly, so processing must tolerate
     * duplicate deliveries. Application failures do not need to implement MessagingException.
     *
     * @param Message $message The message to process, retaining its identifier across delivery attempts.
     *
     * @return void
     *
     * @throws Throwable When processing fails.
     */
    public function handle(Message $message): void;
}
