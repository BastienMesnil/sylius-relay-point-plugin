<?php

declare(strict_types=1);

namespace Tests\Keirontw\SyliusRelayPointPlugin\Provider;

use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\OpeningHours;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointProviderInterface;

final class StubRelayPointProvider implements RelayPointProviderInterface
{
    private const METHOD_CODES = ['stub_relay_standard', 'stub_relay_express'];

    public function supports(string $shippingMethodCode): bool
    {
        return in_array($shippingMethodCode, self::METHOD_CODES, true);
    }

    /** @return RelayPoint[] */
    public function search(RelayPointSearchCriteria $criteria): array
    {
        return [
            new RelayPoint(
                id: 'STUB001',
                name: 'Tabac du Centre',
                street: '10 Rue de la Paix',
                postcode: '75001',
                city: 'Paris',
                countryCode: 'FR',
                latitude: 48.8698,
                longitude: 2.3309,
                distanceInMeters: 350,
                openingHours: [
                    new OpeningHours(day: 'Lundi', hours: '09:00-19:00'),
                    new OpeningHours(day: 'Samedi', hours: '09:00-13:00'),
                ],
                carrierCode: 'stub',
            ),
            new RelayPoint(
                id: 'STUB002',
                name: 'Librairie du Louvre',
                street: '25 Avenue de l\'Opéra',
                postcode: '75001',
                city: 'Paris',
                countryCode: 'FR',
                latitude: 48.8645,
                longitude: 2.3328,
                distanceInMeters: 820,
                openingHours: [
                    new OpeningHours(day: 'Lundi', hours: '10:00-18:00'),
                ],
                carrierCode: 'stub',
            ),
        ];
    }
}
