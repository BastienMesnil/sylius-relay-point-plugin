<?php

declare(strict_types=1);

namespace Tests\Keirontw\SyliusRelayPointPlugin\Unit\Provider;

use Keirontw\SyliusRelayPointPlugin\Provider\Dhl\DhlProvider;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class DhlProviderTest extends TestCase
{
    public function testSupportsConfiguredCodes(): void
    {
        $provider = $this->makeProvider(['dhl_servicepoint_fr', 'dhl_packstation_de']);

        self::assertTrue($provider->supports('dhl_servicepoint_fr'));
        self::assertTrue($provider->supports('dhl_packstation_de'));
        self::assertFalse($provider->supports('dpd_pickup_fr'));
    }

    public function testSearchParsesGeoJsonResponse(): void
    {
        $apiResponse = [
            'features' => [
                [
                    'geometry' => ['coordinates' => [2.3308, 48.8698]],
                    'properties' => [
                        'url' => '/location-finder/v1/locations/12345',
                        'name' => 'DHL ServicePoint Paris Centre',
                        'distance' => 300,
                        'address' => [
                            'streetAddress' => '5 Boulevard Haussmann',
                            'postalCode' => '75009',
                            'addressLocality' => 'Paris',
                            'countryCode' => 'FR',
                        ],
                        'openingHours' => [
                            ['dayOfWeek' => 'https://schema.org/Monday', 'opens' => '08:00', 'closes' => '20:00'],
                            ['dayOfWeek' => 'https://schema.org/Saturday', 'opens' => '09:00', 'closes' => '17:00'],
                        ],
                    ],
                ],
            ],
        ];

        $provider = $this->makeProvider(['dhl_servicepoint_fr'], $apiResponse);
        $criteria = new RelayPointSearchCriteria(postcode: '75009', city: 'Paris', countryCode: 'FR');
        $points = $provider->search($criteria);

        self::assertCount(1, $points);
        self::assertInstanceOf(RelayPoint::class, $points[0]);
        self::assertSame('DHL ServicePoint Paris Centre', $points[0]->name);
        self::assertSame('5 Boulevard Haussmann', $points[0]->street);
        self::assertSame('75009', $points[0]->postcode);
        self::assertSame('FR', $points[0]->countryCode);
        self::assertSame('dhl', $points[0]->carrierCode);
        self::assertEqualsWithDelta(48.8698, $points[0]->latitude, 0.0001);
        self::assertEqualsWithDelta(2.3308, $points[0]->longitude, 0.0001);
        self::assertSame(300, $points[0]->distanceInMeters);
        self::assertCount(2, $points[0]->openingHours);
        self::assertSame('Lundi', $points[0]->openingHours[0]->day);
        self::assertSame('08:00-20:00', $points[0]->openingHours[0]->hours);
        self::assertSame('Samedi', $points[0]->openingHours[1]->day);
    }

    public function testSearchUsesFindByGeoWhenCoordinatesProvided(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::once())
            ->method('request')
            ->with('GET', self::stringContains('find-by-geo'))
            ->willReturn($this->mockEmptyResponse());

        $provider = new DhlProvider($client, new NullLogger(), ['dhl_fr'], 'key');
        $provider->search(new RelayPointSearchCriteria(latitude: 48.856, longitude: 2.352));
    }

    public function testSearchUsesFindByAddressWhenNoCoordinates(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::once())
            ->method('request')
            ->with('GET', self::stringContains('find-by-address'))
            ->willReturn($this->mockEmptyResponse());

        $provider = new DhlProvider($client, new NullLogger(), ['dhl_fr'], 'key');
        $provider->search(new RelayPointSearchCriteria(postcode: '75001', countryCode: 'FR'));
    }

    public function testSearchReturnsEmptyOnHttpError(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willThrowException(new \RuntimeException('timeout'));

        $provider = new DhlProvider($client, new NullLogger(), ['dhl_fr'], 'key');

        self::assertSame([], $provider->search(new RelayPointSearchCriteria(postcode: '75001')));
    }

    private function makeProvider(array $codes, array $responseData = ['features' => []]): DhlProvider
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($responseData);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        return new DhlProvider($client, new NullLogger(), $codes, 'test_key');
    }

    private function mockEmptyResponse(): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['features' => []]);

        return $response;
    }
}
