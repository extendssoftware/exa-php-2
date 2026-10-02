<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Logging;

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Integration\Logging\LoggingModule;
use ExtendsSoftware\ExaPHP\Logging\Logger;
use ExtendsSoftware\ExaPHP\Logging\LogLevel;
use ExtendsSoftware\ExaPHP\Logging\Writer\LogWriter;
use ExtendsSoftware\ExaPHP\Logging\Writer\StreamLogWriter;
use ExtendsSoftware\ExaPHP\Logging\WriterLogger;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

final class LoggingModuleIntegrationTest extends TestCase
{
    public function testBootstrapsWithSharedDefaultServices(): void
    {
        $directory = sys_get_temp_dir() . '/exa-logging-' . bin2hex(random_bytes(8));
        mkdir($directory);
        try {
            $application = new Application($directory);
            $application->registerModule(LoggingModule::class);
            $services = $application->bootstrap();
            $logger = $services->get(Logger::class);
            $writer = $services->get(LogWriter::class);
            $this->assertInstanceOf(WriterLogger::class, $logger);
            $this->assertInstanceOf(StreamLogWriter::class, $writer);
            $this->assertSame($logger, $services->get(Logger::class));
            $this->assertSame($writer, $services->get(LogWriter::class));
            $application->shutdown();
        } finally {
            rmdir($directory);
        }
    }

    public function testApplicationConfigurationOverridesDefaultWriter(): void
    {
        $directory = sys_get_temp_dir() . '/exa-logging-' . bin2hex(random_bytes(8));
        mkdir($directory);
        file_put_contents($directory . '/logging.global.php', <<<'CONFIG'
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Logging\Writer\LogWriter;
use ExtendsSoftware\ExaPHP\Logging\Writer\StreamLogWriter;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;

return ['services' => [
    LogWriter::class => new FactoryDefinition(static fn(): LogWriter => new StreamLogWriter(__DIR__ . '/app.log')),
]];
CONFIG);
        try {
            $application = new Application($directory);
            $application->registerModule(LoggingModule::class);
            $services = $application->bootstrap();
            $services->get(Logger::class)->log(LogLevel::Info, 'Application message', ['id' => 1]);
            $record = json_decode(file_get_contents($directory . '/app.log'), true);
            $this->assertSame('Application message', $record['message']);
            $this->assertSame(['id' => 1], $record['context']);
            $application->shutdown();
        } finally {
            if (is_file($directory . '/app.log')) {
                unlink($directory . '/app.log');
            }
            unlink($directory . '/logging.global.php');
            rmdir($directory);
        }
    }
}
