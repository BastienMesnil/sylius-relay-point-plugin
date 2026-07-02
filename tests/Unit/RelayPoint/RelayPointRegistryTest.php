<?php

declare(strict_types=1);

namespace Tests\Keirontw\SyliusRelayPointPlugin\Unit\RelayPoint;

use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointProviderInterface;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointRegistry;
use PHPUnit\Framework\TestCase;

final class RelayPointRegistryTest extends TestCase
{
    public function testReturnsProviderThatSupportsCode(): void
    {
        $provider = $this->mockProvider(['mondial_relay_fr']);

        $registry = new RelayPointRegistry([$provider]);

        self::assertSame($provider, $registry->getProvider('mondial_relay_fr'));
    }

    public function testReturnsNullWhenNoProviderSupportsCode(): void
    {
        $registry = new RelayPointRegistry([$this->mockProvider(['mondial_relay_fr'])]);

        self::assertNull($registry->getProvider('dpd_pickup'));
    }

    public function testReturnsFirstMatchingProvider(): void
    {
        $first  = $this->mockProvider(['shared_code']);
        $second = $this->mockProvider(['shared_code']);

        $registry = new RelayPointRegistry([$first, $second]);

        self::assertSame($first, $registry->getProvider('shared_code'));
    }

    public function testEmptyRegistryReturnsNull(): void
    {
        $registry = new RelayPointRegistry([]);

        self::assertNull($registry->getProvider('any_code'));
    }

    public function testPicksCorrectProviderAmongMany(): void
    {
        $mr   = $this->mockProvider(['mondial_relay_fr']);
        $cp   = $this->mockProvider(['chronopost_pickup']);
        $inpo = $this->mockProvider(['inpost_fr']);

        $registry = new RelayPointRegistry([$mr, $cp, $inpo]);

        self::assertSame($cp, $registry->getProvider('chronopost_pickup'));
    }

    /** @param string[] $codes */
    private function mockProvider(array $codes): RelayPointProviderInterface
    {
        $provider = $this->createMock(RelayPointProviderInterface::class);
        $provider->method('supports')->willReturnCallback(
            static fn (string $code) => in_array($code, $codes, true),
        );

        return $provider;
    }
}
