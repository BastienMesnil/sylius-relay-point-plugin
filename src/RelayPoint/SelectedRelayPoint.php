<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\RelayPoint;

/**
 * Serializable snapshot of the relay point chosen by the customer.
 * Stored in session under a per-cart key so concurrent sessions don't clash.
 */
final class SelectedRelayPoint
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $street,
        public readonly string $postcode,
        public readonly string $city,
        public readonly string $countryCode,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly string $carrierCode,
        public readonly string $shippingMethodCode,
        public readonly ?int $distanceInMeters = null,
        public readonly array $openingHours = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            street: (string) ($data['street'] ?? ''),
            postcode: (string) ($data['postcode'] ?? ''),
            city: (string) ($data['city'] ?? ''),
            countryCode: (string) ($data['countryCode'] ?? 'FR'),
            latitude: (float) ($data['latitude'] ?? 0),
            longitude: (float) ($data['longitude'] ?? 0),
            carrierCode: (string) ($data['carrierCode'] ?? ''),
            shippingMethodCode: (string) ($data['shippingMethodCode'] ?? ''),
            distanceInMeters: isset($data['distanceInMeters']) ? (int) $data['distanceInMeters'] : null,
            openingHours: (array) ($data['openingHours'] ?? []),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'street' => $this->street,
            'postcode' => $this->postcode,
            'city' => $this->city,
            'countryCode' => $this->countryCode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'carrierCode' => $this->carrierCode,
            'shippingMethodCode' => $this->shippingMethodCode,
            'distanceInMeters' => $this->distanceInMeters,
            'openingHours' => $this->openingHours,
        ];
    }
}
