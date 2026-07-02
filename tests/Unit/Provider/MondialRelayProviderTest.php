<?php

declare(strict_types=1);

namespace Tests\Keirontw\SyliusRelayPointPlugin\Unit\Provider;

use Keirontw\SyliusRelayPointPlugin\Provider\MondialRelay\MondialRelayProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class MondialRelayProviderTest extends TestCase
{
    private MondialRelayProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new MondialRelayProvider(
            account: 'TEST_ACCOUNT',
            password: 'TEST_PASSWORD',
            shippingMethodCodes: ['mondial_relay_fr', 'mondial_relay_be'],
            logger: new NullLogger(),
        );
    }

    public function testSupportsConfiguredCodes(): void
    {
        self::assertTrue($this->provider->supports('mondial_relay_fr'));
        self::assertTrue($this->provider->supports('mondial_relay_be'));
    }

    public function testDoesNotSupportUnknownCode(): void
    {
        self::assertFalse($this->provider->supports('chronopost_pickup'));
        self::assertFalse($this->provider->supports(''));
        self::assertFalse($this->provider->supports('mondial_relay_fr_extra'));
    }

    public function testSearchReturnsEmptyArrayOnSoapError(): void
    {
        // The SOAP client will fail in unit test environment (no network) — expect []
        $criteria = new \Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria(
            postcode: '75001',
            city: 'Paris',
            countryCode: 'FR',
        );

        $result = $this->provider->search($criteria);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    public function testSearchReturnsEmptyArrayWithoutSearchParameters(): void
    {
        $criteria = new \Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria();

        $result = $this->provider->search($criteria);

        self::assertSame([], $result);
    }
}
