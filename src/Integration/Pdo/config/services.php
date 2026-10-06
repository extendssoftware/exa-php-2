<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Integration\Pdo\Factory\PdoFactory;
use ExtendsSoftware\ExaPHP\Integration\Pdo\Transaction\PdoTransactionManager;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\AliasDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ReflectionDefinition;
use ExtendsSoftware\ExaPHP\Transaction\TransactionManager;

return [
    'pdo' => [
        'dsn' => null, // Example: 'mysql:host=localhost;port=3306;dbname=application;charset=utf8mb4'
        'username' => null,
        'password' => null,
        'options' => [],
    ],
    'services' => [
        PDO::class => new FactoryDefinition(new PdoFactory()->create(...)),
        PdoTransactionManager::class => new ReflectionDefinition(PdoTransactionManager::class),
        TransactionManager::class => new AliasDefinition(PdoTransactionManager::class),
    ],
];
