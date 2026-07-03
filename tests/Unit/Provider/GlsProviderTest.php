<?php

declare(strict_types=1);

namespace Tests\Keirontw\SyliusRelayPointPlugin\Unit\Provider;

use Keirontw\SyliusRelayPointPlugin\Provider\Gls\GlsProvider;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class GlsProviderTest extends TestCase
{
    public function testSupportsConfiguredCodes(): void
    {
        $provider = $this->makeProvider(['gls_france', 'gls_germany']);

        self::assertTrue($provider->supports('gls_france'));
        self::assertTrue($provider->supports('gls_germany'));
        self::assertFalse($provider->supports('dpd_pickup_fr'));
    }

    public function testSearchByAddressParsesResponse(): void
    {
        $apiResponse = [
            'ParcelShop' => [
                [
                    'ParcelShopID' => 'GLS_FR-2761234567',
                    'Location' => ['Latitude' => '48.8566', 'Longitude' => '2.3522'],
                    'Address' => [
                        'Name1' => 'Tabac Presse Paris',
                        'Street' => 'Rue de Rivoli',
                        'StreetNumber' => '42',
                        'ZIPCode' => '75001',
                        'City' => 'Paris',
                        'CountryCode' => 'FR',
                    ],
                    'WorkingDay' => [
                        [
                            'DayOfWeek' => 'MON',
                            'OpeningHours' => [
                                'OpeningHours' => [
                                    ['From' => 25200000, 'To' => 64800000],
                                ],
                            ],
                            'Breaks' => [
                                'OpeningHours' => [
                                    ['From' => -3600000, 'To' => -3600000],
                                ],
                            ],
                        ],
                        [
                            'DayOfWeek' => 'SAT',
                            'OpeningHours' => [
                                'OpeningHours' => [
                                    ['From' => 28800000, 'To' => 50400000],
                                ],
                            ],
                            'Breaks' => ['OpeningHours' => []],
                        ],
                    ],
                ],
            ],
        ];

        $provider = $this->makeProvider(['gls_france'], $apiResponse);
        $criteria = new RelayPointSearchCriteria(postcode: '75001', city: 'Paris', countryCode: 'FR');
        $points = $provider->search($criteria);

        self::assertCount(1, $points);
        self::assertInstanceOf(RelayPoint::class, $points[0]);
        self::assertSame('GLS_FR-2761234567', $points[0]->id);
        self::assertSame('Tabac Presse Paris', $points[0]->name);
        self::assertSame('Rue de Rivoli 42', $points[0]->street);
        self::assertSame('75001', $points[0]->postcode);
        self::assertSame('Paris', $points[0]->city);
        self::assertSame('FR', $points[0]->countryCode);
        self::assertSame('gls', $points[0]->carrierCode);
        self::assertEqualsWithDelta(48.8566, $points[0]->latitude, 0.0001);
        self::assertEqualsWithDelta(2.3522, $points[0]->longitude, 0.0001);

        $hours = $points[0]->openingHours;
        self::assertCount(2, $hours);
        self::assertSame('Lundi', $hours[0]->day);
        self::assertSame('08:00-19:00', $hours[0]->hours);
        self::assertSame('Samedi', $hours[1]->day);
        self::assertSame('09:00-15:00', $hours[1]->hours);
    }

    public function testSearchByCoordinatesUsesDistanceEndpoint(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::once())
            ->method('request')
            ->with('POST', self::stringContains('/distance'))
            ->willReturn($this->mockEmptyResponse());

        $provider = new GlsProvider($client, new NullLogger(), ['gls_fr'], 'user', 'pass');
        $provider->search(new RelayPointSearchCriteria(latitude: 48.856, longitude: 2.352));
    }

    public function testSearchByAddressUsesAddressEndpoint(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::once())
            ->method('request')
            ->with('POST', self::stringContains('/address'))
            ->willReturn($this->mockEmptyResponse());

        $provider = new GlsProvider($client, new NullLogger(), ['gls_fr'], 'user', 'pass');
        $provider->search(new RelayPointSearchCriteria(postcode: '75001', countryCode: 'FR'));
    }

    public function testSearchReturnsEmptyWithoutCriteria(): void
    {
        $provider = $this->makeProvider(['gls_fr']);

        self::assertSame([], $provider->search(new RelayPointSearchCriteria()));
    }

    public function testSearchReturnsEmptyOnHttpError(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willThrowException(new \RuntimeException('network error'));

        $provider = new GlsProvider($client, new NullLogger(), ['gls_fr'], 'user', 'pass');

        self::assertSame([], $provider->search(new RelayPointSearchCriteria(postcode: '75001', countryCode: 'FR')));
    }

    public function testDistanceConvertedFromKilometersToMeters(): void
    {
        $apiResponse = [
            'ParcelShop' => [
                [
                    'ParcelShopID' => 'GLS_FR-001',
                    'Location' => ['Latitude' => '48.8', 'Longitude' => '2.3'],
                    'Address' => ['Name1' => 'Shop', 'Street' => 'Rue A', 'StreetNumber' => '1', 'ZIPCode' => '75001', 'City' => 'Paris', 'CountryCode' => 'FR'],
                    'AirlineDistance' => '2.5',
                    'WorkingDay' => [],
                ],
            ],
        ];

        $provider = $this->makeProvider(['gls_fr'], $apiResponse);
        $points = $provider->search(new RelayPointSearchCriteria(latitude: 48.8, longitude: 2.3));

        self::assertSame(2500, $points[0]->distanceInMeters);
    }

    private function makeProvider(array $codes, array $responseData = ['ParcelShop' => []]): GlsProvider
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($responseData);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        return new GlsProvider($client, new NullLogger(), $codes, 'test_user', 'test_pass');
    }

    private function mockEmptyResponse(): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['ParcelShop' => []]);

        return $response;
    }
}
