<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cqrs\Command\Fixture;

/**
 * Identifies a command distinct from its parent for routing tests.
 */
final class ChildCommand extends ParentCommand
{
}
