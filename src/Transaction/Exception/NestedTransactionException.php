<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Transaction\Exception;

use ExtendsSoftware\ExaPHP\Transaction\TransactionException;
use LogicException;

/**
 * Indicates an attempt to start a nested transaction on the same manager.
 */
final class NestedTransactionException extends LogicException implements TransactionException
{
}
