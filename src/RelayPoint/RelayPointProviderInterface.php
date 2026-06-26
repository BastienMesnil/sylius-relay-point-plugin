<?php

declare(strict_types=1);

namespace BastienMesnil\SyliusRelayPointPlugin\RelayPoint;

use BastienMesnil\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use BastienMesnil\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;

/**
 * Implement this interface to plug a carrier (French or foreign) into the
 * relay point selection checkout step. Implementations are auto-discovered:
 * no service id or YAML mapping needs to be registered by hand.
 */
interface RelayPointProviderInterface
{
    public function supports(string $shippingMethodCode): bool;

    /** @return RelayPoint[] */
    public function search(RelayPointSearchCriteria $criteria): array;
}
