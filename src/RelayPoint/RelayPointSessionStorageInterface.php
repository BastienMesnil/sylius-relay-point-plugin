<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\RelayPoint;

interface RelayPointSessionStorageInterface
{
    public function save(SelectedRelayPoint $point, ?string $cartToken = null): void;

    public function get(?string $cartToken = null): ?SelectedRelayPoint;

    public function clear(?string $cartToken = null): void;
}
