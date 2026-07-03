<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Provider\Gls;

use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\OpeningHours;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GlsProvider implements RelayPointProviderInterface
{
    private const DAY_MAP = [
        'MON' => 'Lundi',
        'TUE' => 'Mardi',
        'WED' => 'Mercredi',
        'THU' => 'Jeudi',
        'FRI' => 'Vendredi',
        'SAT' => 'Samedi',
        'SUN' => 'Dimanche',
    ];

    /**
     * @param list<string> $shippingMethodCodes
     */
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly array $shippingMethodCodes,
        private readonly string $username,
        private readonly string $password,
        private readonly string $baseUrl = 'https://shipit.gls-group.eu/backend/rs/parcelshop',
    ) {}

    public function supports(string $shippingMethodCode): bool
    {
        return in_array($shippingMethodCode, $this->shippingMethodCodes, true);
    }

    /** @return RelayPoint[] */
    public function search(RelayPointSearchCriteria $criteria): array
    {
        if ($criteria->latitude !== null && $criteria->longitude !== null) {
            return $this->searchByCoordinates($criteria);
        }

        if ($criteria->postcode !== null || $criteria->city !== null) {
            return $this->searchByAddress($criteria);
        }

        return [];
    }

    /** @return RelayPoint[] */
    private function searchByCoordinates(RelayPointSearchCriteria $criteria): array
    {
        $body = [
            'Latitude' => $criteria->latitude,
            'Longitude' => $criteria->longitude,
            'Distance' => (int) round(($criteria->radiusInMeters ?? 10000) / 1000),
        ];

        $body['MaxNumberOfShops'] = $criteria->limit;

        return $this->request($this->baseUrl . '/distance', $body);
    }

    /** @return RelayPoint[] */
    private function searchByAddress(RelayPointSearchCriteria $criteria): array
    {
        if ($criteria->countryCode === null) {
            return [];
        }

        $body = ['CountryCode' => strtoupper($criteria->countryCode)];

        if ($criteria->postcode !== null) {
            $body['ZIPCode'] = $criteria->postcode;
        }
        if ($criteria->city !== null) {
            $body['City'] = $criteria->city;
        }

        $radiusKm = (int) round(($criteria->radiusInMeters ?? 10000) / 1000);
        $body['Distance'] = max(1, min(50, $radiusKm));

        return $this->request($this->baseUrl . '/address', $body);
    }

    /**
     * @param array<string, mixed> $body
     * @return RelayPoint[]
     */
    private function request(string $url, array $body): array
    {
        try {
            $response = $this->client->request('POST', $url, [
                'auth_basic' => [$this->username, $this->password],
                'headers' => [
                    'Content-Type' => 'application/glsVersion1+json',
                    'Accept' => 'application/glsVersion1+json, application/json',
                ],
                'json' => $body,
            ]);

            $data = $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('GLS ParcelShop API error: ' . $e->getMessage(), ['exception' => $e]);

            return [];
        }

        $shops = $data['ParcelShop'] ?? [];

        return array_map($this->mapShop(...), $shops);
    }

    /** @param array<string, mixed> $shop */
    private function mapShop(array $shop): RelayPoint
    {
        $address = $shop['Address'] ?? [];
        $location = $shop['Location'] ?? [];

        $distanceKm = isset($shop['AirlineDistance']) ? (float) $shop['AirlineDistance'] : null;

        return new RelayPoint(
            id: (string) ($shop['ParcelShopID'] ?? ''),
            name: (string) ($address['Name1'] ?? ''),
            street: trim(($address['Street'] ?? '') . ' ' . ($address['StreetNumber'] ?? '')),
            postcode: (string) ($address['ZIPCode'] ?? ''),
            city: (string) ($address['City'] ?? ''),
            countryCode: strtoupper((string) ($address['CountryCode'] ?? '')),
            latitude: (float) ($location['Latitude'] ?? 0),
            longitude: (float) ($location['Longitude'] ?? 0),
            distanceInMeters: $distanceKm !== null ? (int) round($distanceKm * 1000) : null,
            openingHours: $this->parseOpeningHours($shop['WorkingDay'] ?? []),
            carrierCode: 'gls',
        );
    }

    /**
     * @param array<int, array<string, mixed>> $workingDays
     * @return OpeningHours[]
     */
    private function parseOpeningHours(array $workingDays): array
    {
        $result = [];

        foreach ($workingDays as $day) {
            $dayCode = (string) ($day['DayOfWeek'] ?? '');
            $label = self::DAY_MAP[$dayCode] ?? $dayCode;

            $slots = $day['OpeningHours']['OpeningHours'] ?? [];

            $ranges = [];
            foreach ($slots as $slot) {
                $from = $this->msToTime((int) ($slot['From'] ?? 0));
                $to = $this->msToTime((int) ($slot['To'] ?? 0));

                if ($from !== null && $to !== null) {
                    $ranges[] = $from . '-' . $to;
                }
            }

            if ($ranges !== []) {
                $result[] = new OpeningHours(day: $label, hours: implode(', ', $ranges));
            }
        }

        return $result;
    }

    /**
     * GLS encodes times as milliseconds since midnight with a +1 hour offset.
     * Formula: actual_hours = (ms / 3_600_000) - 1
     */
    private function msToTime(int $ms): ?string
    {
        // Sentinel value used for "no break" → skip
        if ($ms === -3600000) {
            return null;
        }

        $totalMinutes = (int) round(($ms / 3_600_000 + 1) * 60);

        if ($totalMinutes < 0) {
            return null;
        }

        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
