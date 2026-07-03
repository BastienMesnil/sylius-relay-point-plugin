<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Provider\PostNl;

use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\OpeningHours;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PostNlProvider implements RelayPointProviderInterface
{
    private const BASE_URL = 'https://api.postnl.nl/shipment/v2_1/locations';

    private const DAY_MAP = [
        'Monday'    => 'Lundi',
        'Tuesday'   => 'Mardi',
        'Wednesday' => 'Mercredi',
        'Thursday'  => 'Jeudi',
        'Friday'    => 'Vendredi',
        'Saturday'  => 'Samedi',
        'Sunday'    => 'Dimanche',
    ];

    /**
     * @param list<string> $shippingMethodCodes
     */
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly array $shippingMethodCodes,
        private readonly string $apiKey,
        private readonly string $deliveryOptions = 'PG',
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

        if ($criteria->postcode !== null && $criteria->countryCode !== null) {
            return $this->searchByAddress($criteria);
        }

        return [];
    }

    /** @return RelayPoint[] */
    private function searchByCoordinates(RelayPointSearchCriteria $criteria): array
    {
        return $this->request(self::BASE_URL . '/nearest/geocode', [
            'Latitude'        => $criteria->latitude,
            'Longitude'       => $criteria->longitude,
            'CountryCode'     => strtoupper((string) $criteria->countryCode),
            'DeliveryOptions' => $this->deliveryOptions,
        ]);
    }

    /** @return RelayPoint[] */
    private function searchByAddress(RelayPointSearchCriteria $criteria): array
    {
        $params = [
            'CountryCode'     => strtoupper((string) $criteria->countryCode),
            'PostalCode'      => (string) $criteria->postcode,
            'DeliveryOptions' => $this->deliveryOptions,
        ];

        if ($criteria->city !== null) {
            $params['City'] = $criteria->city;
        }

        return $this->request(self::BASE_URL . '/nearest', $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return RelayPoint[]
     */
    private function request(string $url, array $params): array
    {
        try {
            $response = $this->client->request('GET', $url, [
                'headers' => ['apikey' => $this->apiKey],
                'query'   => $params,
            ]);

            $data = $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('PostNL Locations API error: ' . $e->getMessage(), ['exception' => $e]);

            return [];
        }

        $locations = $data['GetLocationsResult']['ResponseLocation'] ?? [];

        // Single-location response is an object, not an array of objects
        if (isset($locations['LocationCode'])) {
            $locations = [$locations];
        }

        if (!is_array($locations)) {
            return [];
        }

        return array_map($this->mapLocation(...), $locations);
    }

    /** @param array<string, mixed> $location */
    private function mapLocation(array $location): RelayPoint
    {
        /** @var array<string, mixed> $address */
        $address = $location['Address'] ?? [];

        $street = trim(
            ((string) ($address['Street'] ?? '')) . ' ' .
            ((string) ($address['HouseNr'] ?? '')) . ' ' .
            ((string) ($address['HouseNrExt'] ?? ''))
        );

        return new RelayPoint(
            id: (string) ($location['LocationCode'] ?? ''),
            name: (string) ($location['Name'] ?? ''),
            street: $street,
            postcode: (string) ($address['Zipcode'] ?? ''),
            city: (string) ($address['City'] ?? ''),
            countryCode: strtoupper((string) ($address['Countrycode'] ?? '')),
            latitude: (float) ($location['Latitude'] ?? 0),
            longitude: (float) ($location['Longitude'] ?? 0),
            distanceInMeters: isset($location['Distance']) ? (int) $location['Distance'] : null,
            openingHours: $this->parseOpeningHours($location['OpeningHours'] ?? []),
            carrierCode: 'postnl',
        );
    }

    /**
     * @param array<string, mixed> $raw
     * @return OpeningHours[]
     */
    private function parseOpeningHours(array $raw): array
    {
        $result = [];

        foreach (self::DAY_MAP as $english => $french) {
            $slot = $raw[$english] ?? null;

            if (!is_array($slot)) {
                continue;
            }

            $from = isset($slot['From']) ? substr((string) $slot['From'], 0, 5) : null;
            $to   = isset($slot['To'])   ? substr((string) $slot['To'],   0, 5) : null;

            if ($from !== null && $to !== null) {
                $result[] = new OpeningHours(day: $french, hours: $from . '-' . $to);
            }
        }

        return $result;
    }
}
