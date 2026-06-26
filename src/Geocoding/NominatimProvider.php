<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Geocoding;

use Keirontw\SyliusRelayPointPlugin\Geocoding\Model\GeocodingResult;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Default geocoding backend, targeting a self-hosted Nominatim instance.
 *
 * The public nominatim.org instance is rate-limited to 1 request/second and
 * its usage policy explicitly forbids SaaS/no-code style usage, so this
 * provider must be pointed at an instance you control (see the docker-compose
 * example shipped with this plugin) or at a compatible commercial service.
 */
final class NominatimProvider implements GeocodingProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $url,
        private readonly ?string $secret = null,
        private readonly string $userAgent = 'SyliusRelayPointPlugin',
        private readonly ?string $contactEmail = null,
    ) {
    }

    public function geocode(string $query): ?GeocodingResult
    {
        $options = [
            'query' => [
                'q' => $query,
                'format' => 'json',
                'limit' => 1,
                'addressdetails' => 1,
            ],
            'headers' => [
                'User-Agent' => $this->contactEmail
                    ? sprintf('%s (%s)', $this->userAgent, $this->contactEmail)
                    : $this->userAgent,
            ],
        ];

        if (null !== $this->contactEmail) {
            $options['query']['email'] = $this->contactEmail;
        }

        if (null !== $this->secret) {
            $options['headers']['X-Nominatim-Secret'] = $this->secret;
        }

        try {
            $response = $this->httpClient->request('GET', $this->url, $options);
            $results = $response->toArray();
        } catch (Throwable $e) {
            $this->logger->error('Nominatim geocoding error: ' . $e->getMessage());

            return null;
        }

        if (empty($results)) {
            return null;
        }

        $result = $results[0];
        $address = $result['address'] ?? [];

        return new GeocodingResult(
            latitude: (float) $result['lat'],
            longitude: (float) $result['lon'],
            postcode: $address['postcode'] ?? null,
            city: $address['city'] ?? $address['town'] ?? $address['village'] ?? null,
            countryCode: isset($address['country_code']) ? strtoupper($address['country_code']) : null,
        );
    }
}
