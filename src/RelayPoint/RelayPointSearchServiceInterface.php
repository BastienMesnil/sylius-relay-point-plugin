<?php

declare(strict_types=1);

namespace BastienMesnil\SyliusRelayPointPlugin\RelayPoint;

use BastienMesnil\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use BastienMesnil\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;

interface RelayPointSearchServiceInterface
{
    /** @return RelayPoint[] */
    public function searchByShippingMethod(string $shippingMethodCode, RelayPointSearchCriteria $criteria): array;
}
