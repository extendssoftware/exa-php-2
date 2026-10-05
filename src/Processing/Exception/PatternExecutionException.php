<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Exception;

use ExtendsSoftware\ExaPHP\Processing\ProcessingException;
use RuntimeException;

/**
 * Indicates that regular expression execution could not complete.
 */
final class PatternExecutionException extends RuntimeException implements ProcessingException
{
}
