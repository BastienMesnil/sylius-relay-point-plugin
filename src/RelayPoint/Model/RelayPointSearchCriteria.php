<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\RelayPoint\Model;

final class RelayPointSearchCriteria
{
    public function __construct(
        public readonly ?string $postcode = null,
        public readonly ?string $city = null,
        public readonly ?string $countryCode = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly int $limit = 10,
        public readonly ?int $radiusInMeters = null,
    ) {
    }
}
