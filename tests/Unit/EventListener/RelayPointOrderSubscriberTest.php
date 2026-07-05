<?php

declare(strict_types=1);

namespace Tests\Keirontw\SyliusRelayPointPlugin\Unit\EventListener;

use Keirontw\SyliusRelayPointPlugin\EventListener\RelayPointOrderSubscriber;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointSessionStorageInterface;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\SelectedRelayPoint;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class RelayPointOrderSubscriberTest extends TestCase
{
    private RelayPointSessionStorageInterface&MockObject $sessionStorage;
    private RelayPointOrderSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->sessionStorage = $this->createMock(RelayPointSessionStorageInterface::class);
        $this->subscriber = new RelayPointOrderSubscriber($this->sessionStorage);
    }

    public function testSubscribesToPreCompleteEvent(): void
    {
        self::assertArrayHasKey('sylius.order.pre_complete', RelayPointOrderSubscriber::getSubscribedEvents());
    }

    public function testCopiesRelayPointToShippingAddress(): void
    {
        $relayPoint = new SelectedRelayPoint(
            id: 'STUB001',
            name: 'Relay Shop',
            street: '1 rue de la Paix',
            postcode: '75001',
            city: 'Paris',
            countryCode: 'FR',
            latitude: 48.8,
            longitude: 2.3,
            carrierCode: 'mondial_relay',
            shippingMethodCode: 'mondial_relay',
        );

        $address = $this->createMock(AddressInterface::class);
        $address->expects(self::once())->method('setCompany')->with('Relay Shop');
        $address->expects(self::once())->method('setStreet')->with('1 rue de la Paix');
        $address->expects(self::once())->method('setPostcode')->with('75001');
        $address->expects(self::once())->method('setCity')->with('Paris');
        $address->expects(self::once())->method('setCountryCode')->with('FR');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getTokenValue')->willReturn('tok_abc');
        $order->method('getShippingAddress')->willReturn($address);

        $this->sessionStorage->method('get')->with('tok_abc')->willReturn($relayPoint);
        $this->sessionStorage->expects(self::once())->method('clear')->with('tok_abc');

        $event = new ResourceControllerEvent($order);
        $this->subscriber->onPreComplete($event);
    }

    public function testDoesNothingWhenNoRelayPointInSession(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getTokenValue')->willReturn('tok_abc');

        $this->sessionStorage->method('get')->with('tok_abc')->willReturn(null);
        $this->sessionStorage->expects(self::never())->method('clear');

        $event = new ResourceControllerEvent($order);
        $this->subscriber->onPreComplete($event);
    }

    public function testDoesNothingWhenSubjectIsNotAnOrder(): void
    {
        $this->sessionStorage->expects(self::never())->method('get');

        $event = new ResourceControllerEvent(new \stdClass());
        $this->subscriber->onPreComplete($event);
    }

    public function testDoesNothingWhenOrderHasNoShippingAddress(): void
    {
        $relayPoint = new SelectedRelayPoint(
            id: 'STUB001',
            name: 'Relay Shop',
            street: '1 rue de la Paix',
            postcode: '75001',
            city: 'Paris',
            countryCode: 'FR',
            latitude: 48.8,
            longitude: 2.3,
            carrierCode: 'mondial_relay',
            shippingMethodCode: 'mondial_relay',
        );

        $order = $this->createMock(OrderInterface::class);
        $order->method('getTokenValue')->willReturn('tok_abc');
        $order->method('getShippingAddress')->willReturn(null);

        $this->sessionStorage->method('get')->with('tok_abc')->willReturn($relayPoint);
        $this->sessionStorage->expects(self::never())->method('clear');

        $event = new ResourceControllerEvent($order);
        $this->subscriber->onPreComplete($event);
    }
}
