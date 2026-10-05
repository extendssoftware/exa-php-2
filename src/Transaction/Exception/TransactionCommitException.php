<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Transaction\Exception;

use ExtendsSoftware\ExaPHP\Transaction\TransactionException;
use RuntimeException;

/**
 * Indicates that transaction commit could not be confirmed.
 */
final class TransactionCommitException extends RuntimeException implements TransactionException
{
}
