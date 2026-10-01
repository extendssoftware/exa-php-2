<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application;

/**
 * Tracks application lifecycle phases.
 *
 * @internal
 */
enum ApplicationState
{
    /**
     * Accepts module registrations before bootstrap.
     */
    case Configuring;

    /**
     * Constructs modules and executes bootstrap hooks.
     */
    case Bootstrapping;

    /**
     * Provides the successfully bootstrapped application.
     */
    case Running;

    /**
     * Executes shutdown hooks.
     */
    case ShuttingDown;

    /**
     * Has completed shutdown, including any failed hooks.
     */
    case Stopped;

    /**
     * Has failed bootstrap and completed any applicable cleanup.
     */
    case Failed;
}
