<?php

declare(strict_types=1);

namespace Tests\Keirontw\SyliusRelayPointPlugin\Unit\RelayPoint;

use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointSessionStorage;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\SelectedRelayPoint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class RelayPointSessionStorageTest extends TestCase
{
    private RelayPointSessionStorage $storage;
    private Session $session;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());

        $request = new Request();
        $request->setSession($this->session);

        $stack = new RequestStack();
        $stack->push($request);

        $this->storage = new RelayPointSessionStorage($stack);
    }

    public function testSaveAndGetWithoutToken(): void
    {
        $point = $this->makePoint('P1', 'mondial_relay_fr');
        $this->storage->save($point);

        $retrieved = $this->storage->get();

        self::assertNotNull($retrieved);
        self::assertSame('P1', $retrieved->id);
        self::assertSame('mondial_relay_fr', $retrieved->shippingMethodCode);
    }

    public function testSaveAndGetWithCartToken(): void
    {
        $point = $this->makePoint('P2', 'chronopost_fr');
        $this->storage->save($point, 'cart-abc');

        self::assertNotNull($this->storage->get('cart-abc'));
        self::assertNull($this->storage->get('other-cart'));
        self::assertNull($this->storage->get());
    }

    public function testClearRemovesEntry(): void
    {
        $this->storage->save($this->makePoint('P3', 'inpost_fr'));
        $this->storage->clear();

        self::assertNull($this->storage->get());
    }

    public function testClearWithTokenOnlyRemovesMatchingEntry(): void
    {
        $this->storage->save($this->makePoint('P4', 'mondial_relay_fr'), 'cart-1');
        $this->storage->save($this->makePoint('P5', 'inpost_fr'), 'cart-2');

        $this->storage->clear('cart-1');

        self::assertNull($this->storage->get('cart-1'));
        self::assertNotNull($this->storage->get('cart-2'));
    }

    public function testGetReturnsNullWhenNothingSaved(): void
    {
        self::assertNull($this->storage->get());
        self::assertNull($this->storage->get('any-token'));
    }

    private function makePoint(string $id, string $methodCode): SelectedRelayPoint
    {
        return new SelectedRelayPoint(
            id: $id,
            name: 'Test Point',
            street: '1 rue Test',
            postcode: '75001',
            city: 'Paris',
            countryCode: 'FR',
            latitude: 48.856,
            longitude: 2.352,
            carrierCode: 'mondial_relay',
            shippingMethodCode: $methodCode,
        );
    }
}
