<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\RelayPoint;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Stores and retrieves the customer's relay point selection from the PHP session.
 *
 * The session key is scoped per cart token so concurrent browser tabs don't clash.
 */
final class RelayPointSessionStorage implements RelayPointSessionStorageInterface
{
    private const SESSION_KEY = 'keirontw_relay_point.selected';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function save(SelectedRelayPoint $point, ?string $cartToken = null): void
    {
        $this->requestStack->getSession()->set(
            $this->key($cartToken),
            $point->toArray(),
        );
    }

    public function get(?string $cartToken = null): ?SelectedRelayPoint
    {
        $data = $this->requestStack->getSession()->get($this->key($cartToken));

        if (!is_array($data) || empty($data['id'])) {
            return null;
        }

        return SelectedRelayPoint::fromArray($data);
    }

    public function clear(?string $cartToken = null): void
    {
        $this->requestStack->getSession()->remove($this->key($cartToken));
    }

    private function key(?string $cartToken): string
    {
        return null !== $cartToken
            ? self::SESSION_KEY . '.' . $cartToken
            : self::SESSION_KEY;
    }
}
