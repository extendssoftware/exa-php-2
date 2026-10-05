<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Transaction;

use Throwable;

/**
 * Identifies transaction lifecycle failures.
 */
interface TransactionException extends Throwable
{
}
