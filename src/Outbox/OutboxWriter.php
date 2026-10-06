<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Outbox;

use ExtendsSoftware\ExaPHP\Outbox\Exception\OutboxWriteException;

/**
 * Appends outgoing messages within the transaction shared with application persistence.
 */
interface OutboxWriter
{
    /**
     * Appends a message to the caller's active transaction.
     *
     * The write must participate in the same transaction as the related application changes. This operation must
     * neither start nor complete a transaction. A successful return means the write is pending: the message becomes
     * available for processing only after commit, and rollback discards the write. No message is delivered here.
     * Missing active transactions and duplicate message identifiers must fail without replacing an existing message.
     * Lower-level persistence failures must be translated with useful context and preserved as the previous exception.
     *
     * @param OutboxMessage $message The outgoing message with an identifier unique within the outbox.
     *
     * @return void
     *
     * @throws OutboxWriteException When no transaction is active, the identifier already exists, or persistence fails.
     */
    public function append(OutboxMessage $message): void;
}
