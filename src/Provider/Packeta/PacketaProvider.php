<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Provider\Packeta;

use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\OpeningHours;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;
use function in_array;
use function is_array;
use function sprintf;

/**
 * Searches Packeta (Zásilkovna) pickup points via their REST API.
 *
 * Packeta is the dominant parcel shop network in Czech Republic, Slovakia,
 * Poland, Hungary, Romania, and is expanding across Germany, Austria, France,
 * Italy, Spain, Belgium, and other EU countries.
 *
 * API key: request at https://client.packeta.com (free for merchants)
 *
 * @see https://docs.packeta.com/
 */
final class PacketaProvider implements RelayPointProviderInterface
{
    private const BASE_URL = 'https://widget.packeta.com/v6/api/pois/json';

    private const CARRIER_CODE = 'packeta';

    private const DAYS = [
        'monday' => 'Lundi',
        'tuesday' => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday' => 'Jeudi',
        'friday' => 'Vendredi',
        'saturday' => 'Samedi',
        'sunday' => 'Dimanche',
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
            'apiKey' => $this->apiKey,
            'country' => strtolower($criteria->countryCode ?? 'fr'),
            'maxCount' => $criteria->limit,
        ];

        if (null !== $criteria->latitude && null !== $criteria->longitude) {
            $params['latlng'] = sprintf('%s,%s', $criteria->latitude, $criteria->longitude);
        } elseif (null !== $criteria->postcode) {
            $params['zip'] = $criteria->postcode;
        } elseif (null !== $criteria->city) {
            $params['city'] = $criteria->city;
        } else {
            return [];
        }

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL, ['query' => $params]);
            $data = $response->toArray();
        } catch (Throwable $e) {
            $this->logger->error('Packeta search error: ' . $e->getMessage());

            return [];
        }

        // Response is either {"data": [...]} or a direct array of points
        $shops = $data['data'] ?? (isset($data[0]) ? $data : []);
        if (!is_array($shops)) {
            return [];
        }

        $points = [];
        foreach ($shops as $shop) {
            $place = $shop['place'] ?? $shop;

            $points[] = new RelayPoint(
                id: (string) ($place['id'] ?? ''),
                name: (string) ($place['name'] ?? ''),
                street: (string) ($place['street'] ?? ''),
                postcode: (string) ($place['zip'] ?? ''),
                city: (string) ($place['city'] ?? ''),
                countryCode: strtoupper((string) ($place['country'] ?? $criteria->countryCode ?? 'FR')),
                latitude: (float) ($place['latitude'] ?? 0),
                longitude: (float) ($place['longitude'] ?? 0),
                distanceInMeters: isset($place['distance']) ? (int) ($place['distance']) : null,
                openingHours: $this->parseOpeningHours($place['openingHours'] ?? []),
                carrierCode: self::CARRIER_CODE,
            );
        }

        return $points;
    }

    /**
     * Packeta opening hours format:
     * {"monday": {"open": "08:00", "close": "20:00"}, "tuesday": {...}, ...}
     *
     * @param array<string, array<string, string>|null> $raw
     * @return OpeningHours[]
     */
    private function parseOpeningHours(array $raw): array
    {
        $openingHours = [];
        foreach (self::DAYS as $key => $label) {
            $slot = $raw[$key] ?? null;
            if (!is_array($slot)) {
                continue;
            }

            $open = $slot['open'] ?? $slot['opens'] ?? null;
            $close = $slot['close'] ?? $slot['closes'] ?? null;

            $openingHours[] = new OpeningHours(
                day: $label,
                hours: ($open && $close) ? sprintf('%s-%s', $open, $close) : 'Fermé',
            );
        }

        return $openingHours;
    }
}
