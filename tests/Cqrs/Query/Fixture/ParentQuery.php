<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cqrs\Query\Fixture;

use ExtendsSoftware\ExaPHP\Cqrs\Query\Query;

/**
 * Provides an intentionally extensible query for testing exact-class routing.
 *
 * @implements Query<mixed>
 */
class ParentQuery implements Query
{
}
