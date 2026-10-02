<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Logging;

/**
 * Identifies the severity of a diagnostic message.
 */
enum LogLevel: string
{
    /**
     * Detailed information for diagnosing behavior.
     */
    case Debug = 'debug';

    /**
     * Information about normal operation.
     */
    case Info = 'info';

    /**
     * A normal but significant occurrence.
     */
    case Notice = 'notice';

    /**
     * An unexpected condition that may require attention.
     */
    case Warning = 'warning';

    /**
     * An operation failed.
     */
    case Error = 'error';

    /**
     * A critical condition affecting system operation.
     */
    case Critical = 'critical';

    /**
     * A condition requiring immediate action.
     */
    case Alert = 'alert';

    /**
     * The system is unusable.
     */
    case Emergency = 'emergency';
}
