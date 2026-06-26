<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\RelayPoint;

use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;

final class RelayPointSearchService implements RelayPointSearchServiceInterface
{
    public function __construct(
        private readonly RelayPointRegistryInterface $registry,
    ) {
    }

    public function searchByShippingMethod(string $shippingMethodCode, RelayPointSearchCriteria $criteria): array
    {
        $provider = $this->registry->getProvider($shippingMethodCode);

        if (null === $provider) {
            return [];
        }

        return $provider->search($criteria);
    }
}
