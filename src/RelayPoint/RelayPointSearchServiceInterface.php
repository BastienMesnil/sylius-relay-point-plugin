<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\RelayPoint;

use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;

interface RelayPointSearchServiceInterface
{
    /** @return RelayPoint[] */
    public function searchByShippingMethod(string $shippingMethodCode, RelayPointSearchCriteria $criteria): array;
}
