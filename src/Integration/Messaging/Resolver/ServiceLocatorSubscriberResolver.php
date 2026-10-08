<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Messaging\Resolver;

use ExtendsSoftware\ExaPHP\Integration\Messaging\Exception\InvalidSubscriberMappingException;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\Exception\SubscriberResolutionException;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\MessageSubscriber;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\SubscriberResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;
use Override;

use function is_string;
use function sprintf;

/**
 * Resolves subscribers lazily through an explicit mapping to service identifiers.
 */
final readonly class ServiceLocatorSubscriberResolver implements SubscriberResolver
{
    /**
     * Creates a resolver without resolving any mapped service.
     *
     * Subscriber identifiers and service identifiers are preserved without normalization. PHP integer array keys
     * represent their corresponding numeric-string subscriber identifiers. Multiple subscribers may share a service.
     * Duplicate array keys are overwritten by PHP before construction and cannot be detected here.
     *
     * @param ServiceLocator $serviceLocator The locator supplying subscriber services.
     * @param array<non-empty-string|int, non-empty-string> $services Subscriber-to-service mappings.
     *
     * @throws InvalidSubscriberMappingException When a subscriber identifier or service identifier is empty or invalid.
     */
    public function __construct(private ServiceLocator $serviceLocator, private array $services)
    {
        foreach ($services as $subscriberId => $serviceId) {
            if ($subscriberId === '' || !is_string($serviceId) || $serviceId === '') {
                throw new InvalidSubscriberMappingException(
                    'Subscriber mappings require non-empty subscriber identifiers and non-empty service strings.',
                );
            }
        }
    }

    /**
     * {@inheritDoc}
     *
     * Unknown subscriber identifiers never fall back to service lookup. The locator controls service lifetime.
     */
    #[Override]
    public function resolve(string $subscriberId): MessageSubscriber
    {
        $serviceId = $this->services[$subscriberId] ?? null;
        if ($serviceId === null) {
            throw new SubscriberResolutionException(sprintf('Subscriber "%s" has no service mapping.', $subscriberId));
        }
        try {
            $subscriber = $this->serviceLocator->get($serviceId);
        } catch (ServiceLocatorException $exception) {
            throw new SubscriberResolutionException(
                sprintf('Unable to resolve subscriber "%s" through service "%s".', $subscriberId, $serviceId),
                0,
                $exception,
            );
        }
        if (!$subscriber instanceof MessageSubscriber) {
            throw new SubscriberResolutionException(
                sprintf(
                    'Service "%s" for subscriber "%s" must implement MessageSubscriber.',
                    $serviceId,
                    $subscriberId,
                ),
            );
        }

        return $subscriber;
    }
}
