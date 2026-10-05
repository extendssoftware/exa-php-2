<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Cli;

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Application\Exception\ApplicationStateException;
use ExtendsSoftware\ExaPHP\Cli\Routing\Exception\CommandNotFoundException;
use ExtendsSoftware\ExaPHP\Integration\Cli\CliModule;
use ExtendsSoftware\ExaPHP\Integration\Cli\CliRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function fclose;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function rewind;
use function rmdir;
use function stream_get_contents;
use function sys_get_temp_dir;
use function unlink;

final class CliHelpIntegrationTest extends TestCase
{
    /** @param list<string> $tokens */
    #[DataProvider('requests')]
    public function testHelpBypassesDispatchAndShutsDown(array $tokens, string $expected): void
    {
        $directory = sys_get_temp_dir() . '/exa-help-' . bin2hex(random_bytes(8));
        mkdir($directory);
        file_put_contents($directory . '/help.global.php', <<<'PHP'
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Cli\Definition\ArgumentDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\CommandDefinition;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use ExtendsSoftware\ExaPHP\Cli\Output\StreamOutput;
use ExtendsSoftware\ExaPHP\Cli\Routing\CommandDispatcher;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;

$streams = new stdClass();
$streams->stdout = fopen('php://memory', 'w+');
$streams->stderr = fopen('php://memory', 'w+');
return [
    'cli' => ['commands' => [
        'greet' => new CommandDefinition('greet', 'missing-handler', 'Say hello', [new ArgumentDefinition('name')]),
    ]],
    'services' => [
        'streams' => new InstanceDefinition($streams),
        Output::class => new InstanceDefinition(new StreamOutput($streams->stdout, $streams->stderr)),
        CommandDispatcher::class => new InstanceDefinition(new stdClass()),
    ],
];
PHP);
        $application = new Application($directory);
        $application->registerModule(CliModule::class);
        $services = $application->bootstrap();
        $streams = $services->get('streams');
        try {
            if ($expected === 'unknown') {
                try {
                    new CliRunner()->run($application, ['console.php', ...$tokens]);
                    $this->fail('Expected command lookup failure.');
                } catch (CommandNotFoundException $exception) {
                    $this->assertStringContainsString('missing', $exception->getMessage());
                }
            } else {
                $this->assertSame(0, new CliRunner()->run($application, ['console.php', ...$tokens]));
                rewind($streams->stdout);
                $this->assertStringContainsString($expected, stream_get_contents($streams->stdout));
            }
            rewind($streams->stderr);
            $this->assertSame('', stream_get_contents($streams->stderr));
            $this->expectException(ApplicationStateException::class);
            $application->bootstrap();
        } finally {
            fclose($streams->stdout);
            fclose($streams->stderr);
            unlink($directory . '/help.global.php');
            rmdir($directory);
        }
    }

    /** @return iterable<array{list<string>, string}> */
    public static function requests(): iterable
    {
        yield [[], 'Commands:'];
        yield [['--list'], 'greet  Say hello'];
        yield [['--help'], 'Commands:'];
        yield [['-h'], 'Commands:'];
        yield [['greet', '--help'], 'Usage: php console.php greet <name>'];
        yield [['greet', '-h'], 'name (required)'];
        yield [['missing', '--help'], 'unknown'];
    }
}
