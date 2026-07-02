<?php

declare(strict_types=1);

namespace Tests\Keirontw\SyliusRelayPointPlugin\Unit\Provider;

use Keirontw\SyliusRelayPointPlugin\Provider\Dpd\DpdProvider;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class DpdProviderTest extends TestCase
{
    public function testSupportsConfiguredCodes(): void
    {
        $provider = $this->makeProvider(['dpd_pickup_fr', 'dpd_pickup_de']);

        self::assertTrue($provider->supports('dpd_pickup_fr'));
        self::assertTrue($provider->supports('dpd_pickup_de'));
        self::assertFalse($provider->supports('mondial_relay_fr'));
    }

    public function testSearchReturnsRelayPoints(): void
    {
        $apiResponse = [
            'parcelShops' => [
                [
                    'parcelShopId' => 'FR001',
                    'company' => 'Tabac Presse',
                    'street' => 'Rue de Rivoli',
                    'houseNo' => '10',
                    'zipCode' => '75001',
                    'city' => 'Paris',
                    'countryCode' => 'FR',
                    'latitude' => 48.856,
                    'longitude' => 2.352,
                    'distance' => 0.5,
                    'openingHours' => [
                        ['weekday' => 1, 'openMorning' => '09:00', 'closeMorning' => '12:00', 'openAfternoon' => '14:00', 'closeAfternoon' => '19:00'],
                        ['weekday' => 6, 'openMorning' => '09:00', 'closeMorning' => '13:00'],
                    ],
                ],
            ],
        ];

        $provider = $this->makeProvider(['dpd_pickup_fr'], $apiResponse);
        $criteria = new RelayPointSearchCriteria(postcode: '75001', countryCode: 'FR');
        $points = $provider->search($criteria);

        self::assertCount(1, $points);
        self::assertInstanceOf(RelayPoint::class, $points[0]);
        self::assertSame('FR001', $points[0]->id);
        self::assertSame('Tabac Presse', $points[0]->name);
        self::assertSame('Rue de Rivoli 10', $points[0]->street);
        self::assertSame('75001', $points[0]->postcode);
        self::assertSame('dpd', $points[0]->carrierCode);
        self::assertSame(500, $points[0]->distanceInMeters);
        self::assertCount(2, $points[0]->openingHours);
        self::assertSame('Lundi', $points[0]->openingHours[0]->day);
        self::assertSame('09:00-12:00, 14:00-19:00', $points[0]->openingHours[0]->hours);
    }

    public function testSearchReturnsEmptyWhenNoCriteriaGiven(): void
    {
        $provider = $this->makeProvider(['dpd_pickup_fr']);
        $result = $provider->search(new RelayPointSearchCriteria());

        self::assertSame([], $result);
    }

    public function testSearchReturnsEmptyOnHttpError(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willThrowException(new \RuntimeException('network error'));

        $provider = new DpdProvider($client, new NullLogger(), ['dpd_pickup_fr'], 'key');
        $result = $provider->search(new RelayPointSearchCriteria(postcode: '75001'));

        self::assertSame([], $result);
    }

    private function makeProvider(array $codes, array $responseData = ['parcelShops' => []]): DpdProvider
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($responseData);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        return new DpdProvider($client, new NullLogger(), $codes, 'test_api_key');
    }
}
