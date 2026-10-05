<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cli\Routing;

use ExtendsSoftware\ExaPHP\Cli\Definition\CommandDefinition;
use ExtendsSoftware\ExaPHP\Cli\Routing\Exception\CommandNotFoundException;
use ExtendsSoftware\ExaPHP\Cli\Routing\Exception\DuplicateCommandException;
use ExtendsSoftware\ExaPHP\Cli\Routing\Exception\InvalidCommandRegistrationException;
use ExtendsSoftware\ExaPHP\Cli\Routing\RegisteredCommands;
use PHPUnit\Framework\TestCase;
use stdClass;

final class RegisteredCommandsTest extends TestCase
{
    public function testRetainsDefinitionIdentityAndRegistrationOrder(): void
    {
        $first = new CommandDefinition('z', 'unregistered');
        $second = new CommandDefinition('a', 'unregistered');
        $registry = new RegisteredCommands([$first, $second]);
        $this->assertSame($first, $registry->get('z'));
        $this->assertSame([$first, $second], $registry->all());
        $this->assertSame([], new RegisteredCommands()->all());
    }

    public function testNamesAreCaseSensitive(): void
    {
        $registry = new RegisteredCommands([new CommandDefinition('run', 'handler')]);
        $this->expectException(CommandNotFoundException::class);
        $registry->get('Run');
    }

    public function testRejectsDuplicateNames(): void
    {
        $this->expectException(DuplicateCommandException::class);
        new RegisteredCommands([new CommandDefinition('run', 'first'), new CommandDefinition('run', 'second')]);
    }

    public function testRejectsNamedRegistrationMaps(): void
    {
        $this->expectException(InvalidCommandRegistrationException::class);
        new RegisteredCommands(['run' => new CommandDefinition('run', 'handler')]);
    }

    public function testRejectsInvalidEntries(): void
    {
        $this->expectException(InvalidCommandRegistrationException::class);
        new RegisteredCommands([new stdClass()]);
    }
}
