<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Messaging;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function fclose;
use function function_exists;
use function proc_close;
use function proc_open;
use function proc_terminate;
use function stream_get_contents;
use function stream_get_meta_data;
use function stream_set_timeout;

use const PHP_BINARY;

final class MessagingWorkerE2ETest extends TestCase
{
    /** @param list<string> $options */
    #[DataProvider('runs')]
    public function testRunsThroughTheApplicationCliBoundary(
        string $mode,
        array $options,
        int $exitCode,
        string $stdout,
        string $stderr,
    ): void {
        if ($mode === 'signal' && (!function_exists('pcntl_async_signals') || !function_exists('posix_kill'))) {
            self::markTestSkipped('Requires PCNTL and POSIX.');
        }
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/Fixture/worker.php', 'messaging:consume', ...$options],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            env_vars: ['MESSAGING_TEST_MODE' => $mode],
        );
        self::assertIsResource($process);
        try {
            stream_set_timeout($pipes[1], 5);
            stream_set_timeout($pipes[2], 5);
            self::assertSame($stdout, stream_get_contents($pipes[1]));
            self::assertFalse(stream_get_meta_data($pipes[1])['timed_out']);
            self::assertSame($stderr, stream_get_contents($pipes[2]));
            self::assertFalse(stream_get_meta_data($pipes[2])['timed_out']);
        } finally {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            $actualExitCode = proc_close($process);
        }
        self::assertSame($exitCode, $actualExitCode);
    }

    /** @return iterable<string, array{string, list<string>, int, string, string}> */
    public static function runs(): iterable
    {
        yield 'once empty' => ['empty', ['--once'], 0, '', ''];
        yield 'once delivered' => ['delivery', ['--once'], 0, "delivered\nacknowledged\n", ''];
        yield 'failure' => ['failure', [], 1, '', "Error: Command execution failed.\n"];
        yield 'stop during delivery' => ['signal', [], 0, "delivered\nacknowledged\n", ''];
    }
}
