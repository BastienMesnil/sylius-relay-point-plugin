<?php

declare(strict_types=1);

namespace Tests\Keirontw\SyliusRelayPointPlugin\Unit\Provider;

use Keirontw\SyliusRelayPointPlugin\Provider\Chronopost\ChronopostProvider;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ChronopostProviderTest extends TestCase
{
    public function testSupportsConfiguredCodes(): void
    {
        $provider = $this->makeProvider(['chronopost_pickup_fr'], 'chronopost');

        self::assertTrue($provider->supports('chronopost_pickup_fr'));
        self::assertFalse($provider->supports('mondial_relay_fr'));
    }

    public function testShop2shopInstanceSupportsItsOwnCodes(): void
    {
        $provider = $this->makeProvider(['shop2shop_fr', 'shop2shop_be'], 'shop2shop');

        self::assertTrue($provider->supports('shop2shop_fr'));
        self::assertTrue($provider->supports('shop2shop_be'));
        self::assertFalse($provider->supports('chronopost_pickup_fr'));
    }

    public function testSearchReturnsEmptyArrayOnSoapError(): void
    {
        $provider = $this->makeProvider(['chronopost_pickup_fr'], 'chronopost');
        $criteria = new RelayPointSearchCriteria(postcode: '69001', city: 'Lyon', countryCode: 'FR');

        self::assertIsArray($provider->search($criteria));
        self::assertEmpty($provider->search($criteria));
    }

    private function makeProvider(array $codes, string $carrierCode): ChronopostProvider
    {
        return new ChronopostProvider(
            account: 'ACC',
            password: 'PWD',
            shippingMethodCodes: $codes,
            carrierCode: $carrierCode,
            logger: new NullLogger(),
        );
    }
}
