<?php

declare(strict_types=1);

namespace Tests\Keirontw\SyliusRelayPointPlugin\Unit\RelayPoint;

use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointProviderInterface;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointRegistryInterface;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointSearchService;
use PHPUnit\Framework\TestCase;

final class RelayPointSearchServiceTest extends TestCase
{
    public function testDelegatesToProviderFoundInRegistry(): void
    {
        $criteria = new RelayPointSearchCriteria(postcode: '75001', city: 'Paris');
        $expected = [$this->makePoint('001')];

        $provider = $this->createMock(RelayPointProviderInterface::class);
        $provider->expects(self::once())->method('search')->with($criteria)->willReturn($expected);

        $registry = $this->createMock(RelayPointRegistryInterface::class);
        $registry->method('getProvider')->with('mondial_relay_fr')->willReturn($provider);

        $service = new RelayPointSearchService($registry);

        self::assertSame($expected, $service->searchByShippingMethod('mondial_relay_fr', $criteria));
    }

    public function testReturnsEmptyArrayWhenNoProviderFound(): void
    {
        $registry = $this->createMock(RelayPointRegistryInterface::class);
        $registry->method('getProvider')->willReturn(null);

        $service = new RelayPointSearchService($registry);

        self::assertSame([], $service->searchByShippingMethod('unknown_method', new RelayPointSearchCriteria()));
    }

    private function makePoint(string $id): RelayPoint
    {
        return new RelayPoint(
            id: $id, name: 'Test', street: '1 rue Test',
            postcode: '75001', city: 'Paris', countryCode: 'FR',
            latitude: 48.856, longitude: 2.352,
        );
    }
}
