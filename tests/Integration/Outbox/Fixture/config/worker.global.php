<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Outbox\Delivery\MessageDelivery;
use ExtendsSoftware\ExaPHP\Outbox\OutboxMessage;
use ExtendsSoftware\ExaPHP\Outbox\Processing\ClaimedMessage;
use ExtendsSoftware\ExaPHP\Outbox\Processing\Exception\OutboxStoreException;
use ExtendsSoftware\ExaPHP\Outbox\Processing\OutboxStore;
use ExtendsSoftware\ExaPHP\Outbox\Retry\FixedDelayRetryPolicy;
use ExtendsSoftware\ExaPHP\Outbox\Retry\RetryPolicy;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;

return ['services' => [
    OutboxStore::class => new InstanceDefinition(new class implements OutboxStore {
        public function claim(DateInterval $leaseDuration): ?ClaimedMessage
        {
            if (getenv('OUTBOX_TEST_MODE') === 'failure') {
                throw new OutboxStoreException('Private connection details.');
            }
            if (getenv('OUTBOX_TEST_MODE') === 'empty') {
                return null;
            }
            $now = new DateTimeImmutable();

            $message = new OutboxMessage('one', 'test.v1', '{}', $now);

            return new ClaimedMessage($message, 1, 'token', $now->add($leaseDuration));
        }

        public function complete(ClaimedMessage $claim): void
        {
            echo "completed\n";
        }

        public function retry(ClaimedMessage $claim, DateTimeImmutable $availableAt): void
        {
            throw new OutboxStoreException('Unexpected retry.');
        }

        public function fail(ClaimedMessage $claim): void
        {
            throw new OutboxStoreException('Unexpected terminal failure.');
        }
    }),
    MessageDelivery::class => new InstanceDefinition(new class implements MessageDelivery {
        public function deliver(OutboxMessage $message): void
        {
            if (getenv('OUTBOX_TEST_MODE') === 'signal') {
                posix_kill(getmypid(), SIGTERM);
            }
            echo "delivered\n";
        }
    }),
    RetryPolicy::class => new InstanceDefinition(new FixedDelayRetryPolicy(1, 3)),
]];
