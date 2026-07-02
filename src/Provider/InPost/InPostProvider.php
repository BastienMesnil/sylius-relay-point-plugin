<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Provider\InPost;

use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\OpeningHours;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;
use function array_map;
use function implode;
use function in_array;
use function is_array;
use function ltrim;
use function max;
use function round;
use function sprintf;
use function strtolower;
use function ucfirst;

/**
 * Searches InPost parcel lockers and pickup points via the InPost REST API.
 *
 * The API is public and requires no authentication for point searches.
 * Country-specific base URLs must be configured per deployment (FR, PL, IT, ES, etc.).
 *
 * France:  https://api.inpost.fr/v1/points
 * Poland:  https://api-pl-points.easypack24.net/v1/points
 * UK:      https://api.inpost.co.uk/v1/points
 */
final class InPostProvider implements RelayPointProviderInterface
{
    private const CARRIER_CODE = 'inpost';

    /**
     * @param string[] $shippingMethodCodes Sylius shipping method codes routed to this provider.
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly array $shippingMethodCodes,
        private readonly string $baseUrl,
    ) {
    }

    public function supports(string $shippingMethodCode): bool
    {
        return in_array($shippingMethodCode, $this->shippingMethodCodes, true);
    }

    public function search(RelayPointSearchCriteria $criteria): array
    {
        try {
            $params = ['per_page' => $criteria->limit, 'fields' => 'name,address,location,distance,opening_hours,status'];

            if (null !== $criteria->latitude && null !== $criteria->longitude) {
                $params['relative_point'] = sprintf('%s,%s', $criteria->latitude, $criteria->longitude);
                $params['max_distance'] = $this->radiusInMeters($criteria->radiusInMeters);
            } elseif (null !== $criteria->postcode || null !== $criteria->city) {
                $addressParts = array_filter([$criteria->postcode, $criteria->city]);
                $params['address'] = implode(' ', $addressParts);
            } else {
                return [];
            }

            $response = $this->httpClient->request('GET', $this->baseUrl, ['query' => $params]);
            $data = $response->toArray();
        } catch (Throwable $e) {
            $this->logger->error('InPost search error: ' . $e->getMessage());

            return [];
        }

        $items = $data['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $points = [];
        foreach ($items as $item) {
            if (($item['status'] ?? '') !== 'Operating') {
                continue;
            }

            $address = $item['address'] ?? [];
            $location = $item['location'] ?? [];

            $points[] = new RelayPoint(
                id: (string) ($item['name'] ?? ''),
                name: (string) ($item['name'] ?? ''),
                street: ltrim(((string) ($address['line1'] ?? '')) . ' ' . ((string) ($address['line2'] ?? ''))),
                postcode: (string) ($address['zip_code'] ?? ''),
                city: (string) ($address['city'] ?? ''),
                countryCode: (string) ($address['country_code'] ?? $criteria->countryCode ?? 'FR'),
                latitude: (float) ($location['latitude'] ?? 0),
                longitude: (float) ($location['longitude'] ?? 0),
                distanceInMeters: isset($item['distance']) ? (int) $item['distance'] : null,
                openingHours: $this->parseOpeningHours($item['opening_hours'] ?? []),
                carrierCode: self::CARRIER_CODE,
            );
        }

        return $points;
    }

    /**
     * @param array<string, array{from: string, to: string}|null> $raw
     * @return OpeningHours[]
     */
    private function parseOpeningHours(array $raw): array
    {
        $dayLabels = [
            'weekdays' => 'Lundi-Vendredi',
            'monday' => 'Lundi',
            'tuesday' => 'Mardi',
            'wednesday' => 'Mercredi',
            'thursday' => 'Jeudi',
            'friday' => 'Vendredi',
            'saturday' => 'Samedi',
            'sunday' => 'Dimanche',
        ];

        $openingHours = [];
        foreach ($dayLabels as $key => $label) {
            if (!isset($raw[$key]) || !is_array($raw[$key])) {
                continue;
            }

            $slot = $raw[$key];
            $hours = (string) ($slot['from'] ?? '') . '-' . (string) ($slot['to'] ?? '');

            $openingHours[] = new OpeningHours(day: $label, hours: $hours);
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
