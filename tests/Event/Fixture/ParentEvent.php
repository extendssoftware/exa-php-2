<?php

declare(strict_types=1);

// @php-cs-fixer-ignore final_class

namespace ExtendsSoftware\ExaPHP\Tests\Event\Fixture;

use ExtendsSoftware\ExaPHP\Event\Event;

/**
 * Provides an intentionally extensible event for testing exact-class routing.
 */
class ParentEvent implements Event {}
