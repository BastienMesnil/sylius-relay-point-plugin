<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\RelayPoint;

interface RelayPointRegistryInterface
{
    public function getProvider(string $shippingMethodCode): ?RelayPointProviderInterface;
}
