<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Provider\Dpd;

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
use function sprintf;
use function trim;

/**
 * Searches DPD Pickup parcel shops via the DPD EU REST API.
 *
 * Coverage: France, Germany, Belgium, Netherlands, Poland, Spain, Italy,
 * Czech Republic, Slovakia, Hungary, Romania, and 10+ other EU countries.
 *
 * API credentials: request access at https://developer.dpd.com
 * The same API key covers all EU countries.
 *
 * @see https://developer.dpd.com/
 */
final class DpdProvider implements RelayPointProviderInterface
{
    private const BASE_URL = 'https://pickup-eu.dpd.com/api/v1/parcelshops';

    private const CARRIER_CODE = 'dpd';

    private const DAYS = [
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
        7 => 'Dimanche',
    ];

    /**
     * @param string[] $shippingMethodCodes
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly array $shippingMethodCodes,
        private readonly string $apiKey,
    ) {
    }

    public function supports(string $shippingMethodCode): bool
    {
        return in_array($shippingMethodCode, $this->shippingMethodCodes, true);
    }

    public function search(RelayPointSearchCriteria $criteria): array
    {
        $params = [
            'countryCode' => $criteria->countryCode ?? 'FR',
            'limit' => $criteria->limit,
        ];

        if (null !== $criteria->latitude && null !== $criteria->longitude) {
            $params['latitude'] = $criteria->latitude;
            $params['longitude'] = $criteria->longitude;
            $params['radius'] = $this->radiusInKm($criteria->radiusInMeters);
        } elseif (null !== $criteria->postcode) {
            $params['zipCode'] = $criteria->postcode;
        } else {
            return [];
        }

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL, [
                'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
                'query' => $params,
            ]);

            $data = $response->toArray();
        } catch (Throwable $e) {
            $this->logger->error('DPD search error: ' . $e->getMessage());

            return [];
        }

        $shops = $data['parcelShops'] ?? [];
        if (!is_array($shops)) {
            return [];
        }

        $points = [];
        foreach ($shops as $shop) {
            $street = trim(($shop['street'] ?? '') . ' ' . ($shop['houseNo'] ?? ''));

            $points[] = new RelayPoint(
                id: (string) ($shop['parcelShopId'] ?? ''),
                name: (string) ($shop['company'] ?? ''),
                street: $street,
                postcode: (string) ($shop['zipCode'] ?? ''),
                city: (string) ($shop['city'] ?? ''),
                countryCode: (string) ($shop['countryCode'] ?? $criteria->countryCode ?? 'FR'),
                latitude: (float) ($shop['latitude'] ?? 0),
                longitude: (float) ($shop['longitude'] ?? 0),
                distanceInMeters: isset($shop['distance']) ? (int) round((float) $shop['distance'] * 1000) : null,
                openingHours: $this->parseOpeningHours($shop['openingHours'] ?? []),
                carrierCode: self::CARRIER_CODE,
            );
        }

        return $points;
    }

    /**
     * @param array<int, array<string, string>> $raw
     * @return OpeningHours[]
     */
    private function parseOpeningHours(array $raw): array
    {
        $openingHours = [];
        foreach ($raw as $slot) {
            $dayNum = (int) ($slot['weekday'] ?? 0);
            $label = self::DAYS[$dayNum] ?? null;
            if (null === $label) {
                continue;
            }

            $hours = [];
            if (!empty($slot['openMorning']) && !empty($slot['closeMorning'])) {
                $hours[] = sprintf('%s-%s', $slot['openMorning'], $slot['closeMorning']);
            }
            if (!empty($slot['openAfternoon']) && !empty($slot['closeAfternoon'])) {
                $hours[] = sprintf('%s-%s', $slot['openAfternoon'], $slot['closeAfternoon']);
            }

            $openingHours[] = new OpeningHours(
                day: $label,
                hours: !empty($hours) ? implode(', ', $hours) : 'Fermé',
            );
        }

        return $openingHours;
    }

    private function radiusInKm(?int $radiusInMeters): int
    {
        if (null === $radiusInMeters) {
            return 10;
        }

        return max(1, (int) round($radiusInMeters / 1000));
    }
}
