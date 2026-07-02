<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Provider\Dhl;

use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\OpeningHours;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;
use function in_array;
use function is_array;
use function max;
use function round;
use function str_replace;
use function substr;
use function ucfirst;
use function strtolower;

/**
 * Searches DHL ServicePoints and Packstations via the DHL Location Finder REST API.
 *
 * Coverage: worldwide — Germany, France, Belgium, Netherlands, UK, Austria,
 * Switzerland, Poland, Czech Republic, Italy, Spain, and many more.
 *
 * API key: free, register at https://developer.dhl.com
 * The same key covers ServicePoints, Packstations, and Post Offices.
 *
 * @see https://developer.dhl.com/api-reference/location-finder
 */
final class DhlProvider implements RelayPointProviderInterface
{
    private const BASE_URL = 'https://api.dhl.com/location-finder/v1';

    private const CARRIER_CODE = 'dhl';

    // Schema.org day-of-week URIs returned by the DHL API
    private const SCHEMA_DAYS = [
        'Monday' => 'Lundi',
        'Tuesday' => 'Mardi',
        'Wednesday' => 'Mercredi',
        'Thursday' => 'Jeudi',
        'Friday' => 'Vendredi',
        'Saturday' => 'Samedi',
        'Sunday' => 'Dimanche',
    ];

    /**
     * @param string[] $shippingMethodCodes
     * @param string   $serviceType         'parcel:pick-up' for ServicePoints, 'parcel:drop-off-easy' for Packstations
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly array $shippingMethodCodes,
        private readonly string $apiKey,
        private readonly string $serviceType = 'parcel:pick-up',
    ) {
    }

    public function supports(string $shippingMethodCode): bool
    {
        return in_array($shippingMethodCode, $this->shippingMethodCodes, true);
    }

    public function search(RelayPointSearchCriteria $criteria): array
    {
        try {
            if (null !== $criteria->latitude && null !== $criteria->longitude) {
                $endpoint = self::BASE_URL . '/find-by-geo';
                $params = [
                    'lat' => $criteria->latitude,
                    'lng' => $criteria->longitude,
                    'radius' => $this->radiusInMeters($criteria->radiusInMeters),
                    'limit' => $criteria->limit,
                    'serviceType' => $this->serviceType,
                ];
            } else {
                $endpoint = self::BASE_URL . '/find-by-address';
                $params = [
                    'countryCode' => $criteria->countryCode ?? 'FR',
                    'addressLocality' => $criteria->city ?? '',
                    'postalCode' => $criteria->postcode ?? '',
                    'radius' => $this->radiusInMeters($criteria->radiusInMeters),
                    'limit' => $criteria->limit,
                    'serviceType' => $this->serviceType,
                ];
            }

            $response = $this->httpClient->request('GET', $endpoint, [
                'headers' => ['DHL-API-Key' => $this->apiKey],
                'query' => $params,
            ]);

            $data = $response->toArray();
        } catch (Throwable $e) {
            $this->logger->error('DHL search error: ' . $e->getMessage());

            return [];
        }

        $features = $data['features'] ?? [];
        if (!is_array($features)) {
            return [];
        }

        $points = [];
        foreach ($features as $feature) {
            $props = $feature['properties'] ?? [];
            $address = $props['address'] ?? [];
            [$longitude, $latitude] = $feature['geometry']['coordinates'] ?? [0, 0];

            $points[] = new RelayPoint(
                id: (string) ($props['url'] ?? uniqid('dhl_', true)),
                name: (string) ($props['name'] ?? ''),
                street: (string) ($address['streetAddress'] ?? ''),
                postcode: (string) ($address['postalCode'] ?? ''),
                city: (string) ($address['addressLocality'] ?? ''),
                countryCode: (string) ($address['countryCode'] ?? $criteria->countryCode ?? 'FR'),
                latitude: (float) $latitude,
                longitude: (float) $longitude,
                distanceInMeters: isset($props['distance']) ? (int) $props['distance'] : null,
                openingHours: $this->parseOpeningHours($props['openingHours'] ?? []),
                carrierCode: self::CARRIER_CODE,
            );
        }

        return $points;
    }

    /**
     * DHL returns opening hours as an array of schema.org structured objects:
     * [{"dayOfWeek": "https://schema.org/Monday", "opens": "09:00", "closes": "18:00"}]
     *
     * @param array<int, array<string, string>> $raw
     * @return OpeningHours[]
     */
    private function parseOpeningHours(array $raw): array
    {
        $openingHours = [];
        foreach ($raw as $slot) {
            $dayOfWeek = $slot['dayOfWeek'] ?? '';
            // Extract day name from schema.org URI: "https://schema.org/Monday" → "Monday"
            $dayName = substr($dayOfWeek, (int) strrpos($dayOfWeek, '/') + 1);
            $label = self::SCHEMA_DAYS[$dayName] ?? null;
            if (null === $label) {
                continue;
            }

            $openingHours[] = new OpeningHours(
                day: $label,
                hours: sprintf('%s-%s', $slot['opens'] ?? '', $slot['closes'] ?? ''),
            );
        }

        return $openingHours;
    }

    private function radiusInMeters(?int $radiusInMeters): int
    {
        if (null === $radiusInMeters) {
            return 5000;
        }

        return max(500, $radiusInMeters);
    }
}
