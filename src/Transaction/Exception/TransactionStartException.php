<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Transaction\Exception;

use ExtendsSoftware\ExaPHP\Transaction\TransactionException;
use RuntimeException;

/**
 * Indicates that a transaction could not be started.
 */
final class TransactionStartException extends RuntimeException implements TransactionException
{
}
