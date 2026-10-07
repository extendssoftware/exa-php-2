<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception\DeliverySettlementException;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception\MessageReceiveException;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\MessageConsumer;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\ReceivedDelivery;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry\FixedDelayRetryPolicy;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry\RetryPolicy;
use ExtendsSoftware\ExaPHP\Messaging\Message;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\MessageSubscriber;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;

return [
    'services' => [
        MessageConsumer::class => new InstanceDefinition(new class implements MessageConsumer {
            public function receive(): ?ReceivedDelivery
            {
                if (getenv('MESSAGING_TEST_MODE') === 'failure') {
                    throw new MessageReceiveException('Private connection details.');
                }
                if (getenv('MESSAGING_TEST_MODE') === 'empty') {
                    return null;
                }
                $message = new Message('one', 'test.v1', '{}', new DateTimeImmutable());

                return new ReceivedDelivery($message, 'subscriber', 'receipt', 1);
            }

            public function acknowledge(ReceivedDelivery $delivery): void
            {
                echo "acknowledged\n";
            }

            public function retry(ReceivedDelivery $delivery, DateTimeImmutable $availableAt): void
            {
                throw new DeliverySettlementException('Unexpected retry.');
            }

            public function reject(ReceivedDelivery $delivery): void
            {
                throw new DeliverySettlementException('Unexpected rejection.');
            }
        }),
        'handler' => new InstanceDefinition(new class implements MessageSubscriber {
            public function handle(Message $message): void
            {
                if (getenv('MESSAGING_TEST_MODE') === 'signal') {
                    posix_kill(getmypid(), SIGTERM);
                }
                echo "delivered\n";
            }
        }),
        RetryPolicy::class => new InstanceDefinition(new FixedDelayRetryPolicy(1, 3)),
    ],
    'messaging' => ['subscribers' => ['subscriber' => 'handler']],
];
