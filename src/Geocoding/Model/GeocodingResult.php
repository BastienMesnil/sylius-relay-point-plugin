<?php

declare(strict_types=1);

namespace BastienMesnil\SyliusRelayPointPlugin\Geocoding\Model;

final class GeocodingResult
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?string $postcode = null,
        public readonly ?string $city = null,
        public readonly ?string $countryCode = null,
    ) {
    }
}
