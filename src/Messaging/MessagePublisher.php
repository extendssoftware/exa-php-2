<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging;

use ExtendsSoftware\ExaPHP\Messaging\Exception\MessagePublishException;

/**
 * Publishes messages for durable distribution.
 */
interface MessagePublisher
{
    /**
     * Publishes a message and returns after durable acceptance is acknowledged.
     *
     * Preserve the supplied message identity, type, payload, and creation time. Successful return confirms acceptance
     * for distribution, not completion of subscriber processing. A pending uncommitted write is not durable acceptance.
     * A failure does not guarantee rejection: acceptance may have occurred without acknowledgement. Publishing the
     * same message again may result in duplicate delivery. Exactly-once processing and delivery order are not
     * guaranteed.
     * Expected publishing failures must be translated with useful context and their lower-level cause preserved as
     * the previous exception.
     *
     * @param Message $message The message to publish, retaining its identifier across repeated attempts.
     *
     * @return void
     *
     * @throws MessagePublishException When durable acceptance cannot be acknowledged.
     */
    public function publish(Message $message): void;
}
