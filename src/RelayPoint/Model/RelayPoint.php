<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\RelayPoint\Model;

final class RelayPoint
{
    /** @param OpeningHours[] $openingHours */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $street,
        public readonly string $postcode,
        public readonly string $city,
        public readonly string $countryCode,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?int $distanceInMeters = null,
        public readonly array $openingHours = [],
        public readonly string $carrierCode = '',
    ) {
    }
}
