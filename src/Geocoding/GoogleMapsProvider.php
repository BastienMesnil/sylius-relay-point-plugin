<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Geocoding;

use Keirontw\SyliusRelayPointPlugin\Geocoding\Model\GeocodingResult;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Geocoding backend using the Google Maps Geocoding API.
 * Requires a valid API key (GOOGLE_MAPS_API_KEY env var).
 * Worldwide coverage and high accuracy; billed beyond the free monthly quota.
 */
final class GoogleMapsProvider implements GeocodingProviderInterface
{
    private const ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiKey,
    ) {
    }

    public function geocode(string $query): ?GeocodingResult
    {
        try {
            $response = $this->httpClient->request('GET', self::ENDPOINT, [
                'query' => [
                    'address' => $query,
                    'key' => $this->apiKey,
                ],
            ]);

            $data = $response->toArray();
        } catch (Throwable $e) {
            $this->logger->error('Google Maps geocoding error: ' . $e->getMessage());

            return null;
        }

        if (($data['status'] ?? '') !== 'OK' || empty($data['results'])) {
            return null;
        }

        $result = $data['results'][0];
        $location = $result['geometry']['location'];

        $postcode = null;
        $city = null;
        $countryCode = null;

        foreach ($result['address_components'] ?? [] as $component) {
            $types = $component['types'] ?? [];
            if (in_array('postal_code', $types, true)) {
                $postcode = $component['short_name'];
            } elseif (in_array('locality', $types, true)) {
                $city = $component['long_name'];
            } elseif (in_array('country', $types, true)) {
                $countryCode = strtoupper($component['short_name']);
            }
        }

        return new GeocodingResult(
            latitude: (float) $location['lat'],
            longitude: (float) $location['lng'],
            postcode: $postcode,
            city: $city,
            countryCode: $countryCode,
        );
    }
}
