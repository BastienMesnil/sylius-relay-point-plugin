<?php

declare(strict_types=1);

namespace Tests\Keirontw\SyliusRelayPointPlugin\Unit\Provider;

use Keirontw\SyliusRelayPointPlugin\Provider\PostNl\PostNlProvider;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class PostNlProviderTest extends TestCase
{
    public function testSupportsConfiguredCodes(): void
    {
        $provider = $this->makeProvider(['postnl_nl', 'postnl_be']);

        self::assertTrue($provider->supports('postnl_nl'));
        self::assertTrue($provider->supports('postnl_be'));
        self::assertFalse($provider->supports('gls_france'));
    }

    public function testSearchByAddressParsesResponse(): void
    {
        $apiResponse = [
            'GetLocationsResult' => [
                'ResponseLocation' => [
                    [
                        'LocationCode' => 163043,
                        'Name'         => 'Tonys Tabakszaak',
                        'Address'      => [
                            'Street'      => 'Siriusdreef',
                            'HouseNr'     => 42,
                            'HouseNrExt'  => '',
                            'Zipcode'     => '2132WT',
                            'City'        => 'Hoofddorp',
                            'Countrycode' => 'NL',
                        ],
                        'Latitude'  => 52.30,
                        'Longitude' => 4.68,
                        'Distance'  => 102,
                        'OpeningHours' => [
                            'Monday'    => ['From' => '09:00:00', 'To' => '18:00:00'],
                            'Tuesday'   => ['From' => '09:00:00', 'To' => '18:00:00'],
                            'Saturday'  => ['From' => '09:00:00', 'To' => '13:00:00'],
                            'Sunday'    => null,
                        ],
                    ],
                ],
            ],
        ];

        $provider = $this->makeProvider(['postnl_nl'], $apiResponse);
        $criteria = new RelayPointSearchCriteria(postcode: '2132WT', countryCode: 'NL');
        $points = $provider->search($criteria);

        self::assertCount(1, $points);
        self::assertInstanceOf(RelayPoint::class, $points[0]);
        self::assertSame('163043', $points[0]->id);
        self::assertSame('Tonys Tabakszaak', $points[0]->name);
        self::assertSame('Siriusdreef 42', trim($points[0]->street));
        self::assertSame('2132WT', $points[0]->postcode);
        self::assertSame('Hoofddorp', $points[0]->city);
        self::assertSame('NL', $points[0]->countryCode);
        self::assertSame('postnl', $points[0]->carrierCode);
        self::assertEqualsWithDelta(52.30, $points[0]->latitude, 0.001);
        self::assertEqualsWithDelta(4.68, $points[0]->longitude, 0.001);
        self::assertSame(102, $points[0]->distanceInMeters);

        $hours = $points[0]->openingHours;
        self::assertCount(3, $hours); // Sunday null → skipped
        self::assertSame('Lundi', $hours[0]->day);
        self::assertSame('09:00-18:00', $hours[0]->hours);
        self::assertSame('Samedi', $hours[2]->day);
        self::assertSame('09:00-13:00', $hours[2]->hours);
    }

    public function testSingleLocationResponseIsNormalised(): void
    {
        // PostNL returns a single object (not array) when only one result
        $apiResponse = [
            'GetLocationsResult' => [
                'ResponseLocation' => [
                    'LocationCode' => 999,
                    'Name'         => 'Solo Shop',
                    'Address'      => ['Street' => 'Main St', 'HouseNr' => 1, 'HouseNrExt' => '', 'Zipcode' => '1000AA', 'City' => 'Amsterdam', 'Countrycode' => 'NL'],
                    'Latitude'     => 52.37,
                    'Longitude'    => 4.89,
                    'Distance'     => 50,
                    'OpeningHours' => [],
                ],
            ],
        ];

        $provider = $this->makeProvider(['postnl_nl'], $apiResponse);
        $points = $provider->search(new RelayPointSearchCriteria(postcode: '1000AA', countryCode: 'NL'));

        self::assertCount(1, $points);
        self::assertSame('999', $points[0]->id);
    }

    public function testSearchByCoordinatesUsesGeoEndpoint(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::once())
            ->method('request')
            ->with('GET', self::stringContains('/nearest/geocode'))
            ->willReturn($this->mockEmptyResponse());

        $provider = new PostNlProvider($client, new NullLogger(), ['postnl_nl'], 'key');
        $provider->search(new RelayPointSearchCriteria(latitude: 52.37, longitude: 4.89, countryCode: 'NL'));
    }

    public function testSearchByAddressUsesNearestEndpoint(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::once())
            ->method('request')
            ->with('GET', self::stringContains('/nearest'))
            ->willReturn($this->mockEmptyResponse());

        $provider = new PostNlProvider($client, new NullLogger(), ['postnl_nl'], 'key');
        $provider->search(new RelayPointSearchCriteria(postcode: '2132WT', countryCode: 'NL'));
    }

    public function testSearchReturnsEmptyWithoutCriteria(): void
    {
        $provider = $this->makeProvider(['postnl_nl']);

        self::assertSame([], $provider->search(new RelayPointSearchCriteria()));
    }

    public function testSearchReturnsEmptyOnHttpError(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willThrowException(new \RuntimeException('timeout'));

        $provider = new PostNlProvider($client, new NullLogger(), ['postnl_nl'], 'key');

        self::assertSame([], $provider->search(new RelayPointSearchCriteria(postcode: '2132WT', countryCode: 'NL')));
    }

    private function makeProvider(array $codes, array $responseData = ['GetLocationsResult' => ['ResponseLocation' => []]]): PostNlProvider
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($responseData);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        return new PostNlProvider($client, new NullLogger(), $codes, 'test_key');
    }

    private function mockEmptyResponse(): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['GetLocationsResult' => ['ResponseLocation' => []]]);

        return $response;
    }
}
