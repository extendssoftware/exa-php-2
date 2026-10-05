<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cli;

use ExtendsSoftware\ExaPHP\Cli\ExitCode;
use PHPUnit\Framework\TestCase;

final class ExitCodeTest extends TestCase
{
    public function testConventionalExitCodesHaveStableIntegerValues(): void
    {
        $this->assertSame(0, ExitCode::Success->value);
        $this->assertSame(1, ExitCode::Failure->value);
        $this->assertSame(2, ExitCode::InvalidUsage->value);
    }
}
