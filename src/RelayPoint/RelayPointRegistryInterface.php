<?php

declare(strict_types=1);

namespace BastienMesnil\SyliusRelayPointPlugin\RelayPoint;

interface RelayPointRegistryInterface
{
    public function getProvider(string $shippingMethodCode): ?RelayPointProviderInterface;
}
