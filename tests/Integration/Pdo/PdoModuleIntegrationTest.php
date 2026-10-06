<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Pdo;

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Integration\Pdo\PdoModule;
use ExtendsSoftware\ExaPHP\Integration\Pdo\Transaction\PdoTransactionManager;
use ExtendsSoftware\ExaPHP\Transaction\TransactionManager;
use PDO;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

final class PdoModuleIntegrationTest extends TestCase
{
    public function testApplicationConfigurationCreatesSharedConnectionAndTransactionServices(): void
    {
        $directory = sys_get_temp_dir() . '/exa-pdo-' . bin2hex(random_bytes(8));
        mkdir($directory);
        file_put_contents($directory . '/pdo.global.php', <<<'CONFIG'
<?php

declare(strict_types=1);

return ['pdo' => ['dsn' => 'sqlite::memory:']];
CONFIG);
        try {
            $application = new Application($directory);
            $application->registerModule(PdoModule::class);
            $services = $application->bootstrap();
            $pdo = $services->get(PDO::class);
            $manager = $services->get(TransactionManager::class);
            $this->assertInstanceOf(PdoTransactionManager::class, $manager);
            $this->assertSame($pdo, $services->get(PDO::class));
            $this->assertSame($manager, $services->get(TransactionManager::class));
            $this->assertSame($manager, $services->get(PdoTransactionManager::class));
            $manager->transactional(function () use ($pdo): void {
                $this->assertTrue($pdo->inTransaction());
            });
            $this->assertFalse($pdo->inTransaction());
            $application->shutdown();
        } finally {
            unlink($directory . '/pdo.global.php');
            rmdir($directory);
        }
    }
}
