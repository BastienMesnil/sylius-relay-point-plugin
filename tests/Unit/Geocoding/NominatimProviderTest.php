<?php

declare(strict_types=1);

namespace Tests\Keirontw\SyliusRelayPointPlugin\Unit\Geocoding;

use Keirontw\SyliusRelayPointPlugin\Geocoding\Model\GeocodingResult;
use Keirontw\SyliusRelayPointPlugin\Geocoding\NominatimProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class NominatimProviderTest extends TestCase
{
    public function testReturnsGeocodingResultOnSuccess(): void
    {
        $response = $this->mockResponse([[
            'lat' => '48.8566',
            'lon' => '2.3522',
            'address' => [
                'postcode' => '75001',
                'city' => 'Paris',
                'country_code' => 'fr',
            ],
        ]]);

        $provider = new NominatimProvider(
            $this->mockClient($response),
            new NullLogger(),
            url: 'https://nominatim.example.com/search',
        );

        $result = $provider->geocode('Paris');

        self::assertInstanceOf(GeocodingResult::class, $result);
        self::assertEqualsWithDelta(48.8566, $result->latitude, 0.0001);
        self::assertEqualsWithDelta(2.3522, $result->longitude, 0.0001);
        self::assertSame('FR', $result->countryCode);
    }

    public function testReturnsNullOnEmptyResponse(): void
    {
        $response = $this->mockResponse([]);
        $provider = new NominatimProvider(
            $this->mockClient($response),
            new NullLogger(),
            url: 'https://nominatim.example.com/search',
        );

        self::assertNull($provider->geocode('nowhere'));
    }

    public function testReturnsNullOnHttpException(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willThrowException(new \RuntimeException('timeout'));

        $provider = new NominatimProvider(
            $client,
            new NullLogger(),
            url: 'https://nominatim.example.com/search',
        );

        self::assertNull($provider->geocode('Paris'));
    }

    private function mockResponse(array $data): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($data);

        return $response;
    }

    private function mockClient(ResponseInterface $response): HttpClientInterface
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        return $client;
    }
}
