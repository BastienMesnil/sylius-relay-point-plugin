<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Geocoding;

use Keirontw\SyliusRelayPointPlugin\Geocoding\Model\GeocodingResult;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Geocoding backend targeting a self-hosted Photon instance (komoot/photon).
 * Photon is an OSM-based geocoder, lighter to self-host than Nominatim and
 * supports per-country data imports. Worldwide coverage.
 *
 * Returns GeoJSON — coordinates are [longitude, latitude].
 */
final class PhotonProvider implements GeocodingProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $url,
        private readonly ?string $lang = null,
    ) {
    }

    public function geocode(string $query): ?GeocodingResult
    {
        $queryParams = ['q' => $query, 'limit' => 1];
        if (null !== $this->lang) {
            $queryParams['lang'] = $this->lang;
        }

        try {
            $response = $this->httpClient->request('GET', $this->url, [
                'query' => $queryParams,
            ]);

            $data = $response->toArray();
        } catch (Throwable $e) {
            $this->logger->error('Photon geocoding error: ' . $e->getMessage());

            return null;
        }

        $features = $data['features'] ?? [];
        if (empty($features)) {
            return null;
        }

        $feature = $features[0];
        [$longitude, $latitude] = $feature['geometry']['coordinates'];
        $properties = $feature['properties'] ?? [];

        return new GeocodingResult(
            latitude: (float) $latitude,
            longitude: (float) $longitude,
            postcode: $properties['postcode'] ?? null,
            city: $properties['city'] ?? $properties['name'] ?? null,
            countryCode: isset($properties['countrycode']) ? strtoupper($properties['countrycode']) : null,
        );
    }
}
