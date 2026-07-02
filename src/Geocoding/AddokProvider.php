<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Geocoding;

use Keirontw\SyliusRelayPointPlugin\Geocoding\Model\GeocodingResult;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Geocoding backend targeting the French Base Adresse Nationale (BAN).
 *
 * The public endpoint (api-adresse.data.gouv.fr) is a free official French
 * government service with no API key required and generous rate limits.
 * Only resolves French addresses — use another provider for international.
 * A self-hosted instance can be substituted via the url config key.
 */
final class AddokProvider implements GeocodingProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $url = 'https://api-adresse.data.gouv.fr/search/',
    ) {
    }

    public function geocode(string $query): ?GeocodingResult
    {
        try {
            $response = $this->httpClient->request('GET', $this->url, [
                'query' => [
                    'q' => $query,
                    'limit' => 1,
                ],
            ]);

            $data = $response->toArray();
        } catch (Throwable $e) {
            $this->logger->error('Addok geocoding error: ' . $e->getMessage());

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
            city: $properties['city'] ?? null,
            countryCode: 'FR',
        );
    }
}
