<?php

declare(strict_types=1);

namespace Tests\Keirontw\SyliusRelayPointPlugin\Unit\Geocoding;

use Keirontw\SyliusRelayPointPlugin\Geocoding\AddokProvider;
use Keirontw\SyliusRelayPointPlugin\Geocoding\Model\GeocodingResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class AddokProviderTest extends TestCase
{
    public function testReturnsGeocodingResultOnSuccess(): void
    {
        $response = $this->mockResponse([
            'features' => [[
                'geometry' => ['coordinates' => [2.3308, 48.8698]],
                'properties' => ['postcode' => '75001', 'city' => 'Paris'],
            ]],
        ]);

        $provider = new AddokProvider($this->mockClient($response), new NullLogger());
        $result = $provider->geocode('10 rue de la Paix 75001 Paris');

        self::assertInstanceOf(GeocodingResult::class, $result);
        self::assertEqualsWithDelta(48.8698, $result->latitude, 0.0001);
        self::assertEqualsWithDelta(2.3308, $result->longitude, 0.0001);
        self::assertSame('75001', $result->postcode);
        self::assertSame('Paris', $result->city);
        self::assertSame('FR', $result->countryCode);
    }

    public function testReturnsNullWhenNoFeatures(): void
    {
        $response = $this->mockResponse(['features' => []]);
        $provider = new AddokProvider($this->mockClient($response), new NullLogger());

        self::assertNull($provider->geocode('unknown address'));
    }

    public function testReturnsNullOnHttpError(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willThrowException(new \RuntimeException('network error'));

        $provider = new AddokProvider($client, new NullLogger());

        self::assertNull($provider->geocode('anything'));
    }

    public function testAlwaysSetsFrenchCountryCode(): void
    {
        $response = $this->mockResponse([
            'features' => [[
                'geometry' => ['coordinates' => [2.35, 48.85]],
                'properties' => ['postcode' => '75000', 'city' => 'Paris'],
            ]],
        ]);

        $provider = new AddokProvider($this->mockClient($response), new NullLogger());

        self::assertSame('FR', $provider->geocode('Paris')->countryCode);
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
