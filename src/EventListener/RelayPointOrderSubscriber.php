<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\EventListener;

use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointSessionStorage;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Webmozart\Assert\Assert;

/**
 * Copies the relay point chosen by the customer (stored in session) onto the
 * shipping address of the order when the checkout is completed.
 *
 * This subscriber is optional. It can be disabled via bundle config:
 *
 *   keirontw_sylius_relay_point:
 *       apply_relay_point_to_order: false
 */
final class RelayPointOrderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RelayPointSessionStorage $sessionStorage,
    ) {
    }

    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.sylius_order_checkout.completed.complete' => 'onCheckoutComplete',
        ];
    }

    public function onCheckoutComplete(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        Assert::isInstanceOf($order, OrderInterface::class);

        $relayPoint = $this->sessionStorage->get($order->getTokenValue());

        if ($relayPoint === null) {
            return;
        }

        $address = $order->getShippingAddress();

        if (!$address instanceof AddressInterface) {
            return;
        }

        $address->setCompany($relayPoint->name);
        $address->setStreet($relayPoint->street);
        $address->setPostcode($relayPoint->postcode);
        $address->setCity($relayPoint->city);
        $address->setCountryCode($relayPoint->countryCode);

        $this->sessionStorage->clear($order->getTokenValue());
    }
}
