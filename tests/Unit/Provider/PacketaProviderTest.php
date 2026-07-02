<?php

declare(strict_types=1);

namespace Tests\Keirontw\SyliusRelayPointPlugin\Unit\Provider;

use Keirontw\SyliusRelayPointPlugin\Provider\Packeta\PacketaProvider;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class PacketaProviderTest extends TestCase
{
    public function testSupportsConfiguredCodes(): void
    {
        $provider = $this->makeProvider(['packeta_cz', 'packeta_sk', 'packeta_fr']);

        self::assertTrue($provider->supports('packeta_cz'));
        self::assertTrue($provider->supports('packeta_fr'));
        self::assertFalse($provider->supports('dpd_pickup_fr'));
    }

    public function testSearchParsesDataArray(): void
    {
        $apiResponse = [
            'data' => [
                [
                    'id' => 'PKT001',
                    'name' => 'Packeta Point Praha',
                    'street' => 'Václavské náměstí 1',
                    'zip' => '11000',
                    'city' => 'Praha',
                    'country' => 'cz',
                    'latitude' => 50.0808,
                    'longitude' => 14.4267,
                    'distance' => 800,
                    'openingHours' => [
                        'monday' => ['open' => '08:00', 'close' => '20:00'],
                        'saturday' => ['open' => '09:00', 'close' => '14:00'],
                        'sunday' => null,
                    ],
                ],
            ],
        ];

        $provider = $this->makeProvider(['packeta_cz'], $apiResponse);
        $criteria = new RelayPointSearchCriteria(postcode: '11000', countryCode: 'CZ');
        $points = $provider->search($criteria);

        self::assertCount(1, $points);
        self::assertInstanceOf(RelayPoint::class, $points[0]);
        self::assertSame('PKT001', $points[0]->id);
        self::assertSame('Packeta Point Praha', $points[0]->name);
        self::assertSame('CZ', $points[0]->countryCode);
        self::assertSame('packeta', $points[0]->carrierCode);
        self::assertSame(800, $points[0]->distanceInMeters);

        $hours = $points[0]->openingHours;
        self::assertCount(2, $hours); // sunday is null → skipped
        self::assertSame('Lundi', $hours[0]->day);
        self::assertSame('08:00-20:00', $hours[0]->hours);
        self::assertSame('Samedi', $hours[1]->day);
    }

    public function testSearchReturnsEmptyWithoutCriteria(): void
    {
        $provider = $this->makeProvider(['packeta_fr']);

        self::assertSame([], $provider->search(new RelayPointSearchCriteria()));
    }

    public function testSearchReturnsEmptyOnHttpError(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willThrowException(new \RuntimeException('network error'));

        $provider = new PacketaProvider($client, new NullLogger(), ['packeta_fr'], 'key');

        self::assertSame([], $provider->search(new RelayPointSearchCriteria(postcode: '75001')));
    }

    private function makeProvider(array $codes, array $responseData = ['data' => []]): PacketaProvider
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($responseData);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        return new PacketaProvider($client, new NullLogger(), $codes, 'test_key');
    }
}
