<?php

declare(strict_types=1);

namespace BastienMesnil\SyliusRelayPointPlugin\RelayPoint;

final class RelayPointRegistry implements RelayPointRegistryInterface
{
    /** @param iterable<RelayPointProviderInterface> $providers */
    public function __construct(
        private readonly iterable $providers,
    ) {
    }

    public function getProvider(string $shippingMethodCode): ?RelayPointProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($shippingMethodCode)) {
                return $provider;
            }
        }

        return null;
    }
}
