<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Transaction;

use ExtendsSoftware\ExaPHP\Cqrs\Command\Command;
use ExtendsSoftware\ExaPHP\Cqrs\Command\CommandHandler;
use ExtendsSoftware\ExaPHP\Cqrs\Command\SynchronousCommandBus;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;
use ExtendsSoftware\ExaPHP\Integration\Transaction\Middleware\TransactionalCommandMiddleware;
use ExtendsSoftware\ExaPHP\Transaction\TransactionManager;
use PHPUnit\Framework\TestCase;

final class TransactionalCommandMiddlewareIntegrationTest extends TestCase
{
    public function testCommandBusRunsHandlerWithinTransactionalOperation(): void
    {
        $calls = [];
        $command = $this->createStub(Command::class);
        $context = new DispatchContext();
        $handler = $this->createMock(CommandHandler::class);
        $handler->expects($this->once())->method('handle')->with($command)->willReturnCallback(
            static function () use (&$calls): void {
                $calls[] = 'handle';
            },
        );
        $manager = $this->createMock(TransactionManager::class);
        $manager->expects($this->once())->method('transactional')->willReturnCallback(
            static function (callable $operation) use (&$calls): mixed {
                $calls[] = 'enter';
                $result = $operation();
                $calls[] = 'leave';

                return $result;
            },
        );
        $bus = new SynchronousCommandBus(
            [$command::class => $handler],
            [new TransactionalCommandMiddleware($manager)],
        );
        $bus->dispatch($command, $context);
        $this->assertSame(['enter', 'handle', 'leave'], $calls);
    }
}
