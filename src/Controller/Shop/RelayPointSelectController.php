<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Controller\Shop;

use Keirontw\SyliusRelayPointPlugin\RelayPoint\SelectedRelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointSessionStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Persists the relay point chosen by the customer into the session.
 *
 * POST /{_locale}/relay-points/select
 * Body (JSON):
 * {
 *   "shippingMethodCode": "mondial_relay_fr",
 *   "cartToken": "abc123",          // optional, scopes the session key per cart
 *   "point": {
 *     "id": "…", "name": "…", "street": "…", "postcode": "…",
 *     "city": "…", "countryCode": "FR", "latitude": 48.8, "longitude": 2.3,
 *     "carrierCode": "mondial_relay", "distanceInMeters": 500, "openingHours": []
 *   }
 * }
 *
 * The host application reads the selection back via RelayPointSessionStorage::get()
 * during checkout completion and updates the Sylius order shipping address accordingly.
 */
final class RelayPointSelectController extends AbstractController
{
    public function __construct(
        private readonly RelayPointSessionStorage $storage,
    ) {
    }

    public function select(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        if (!is_array($body) || empty($body['point']) || empty($body['shippingMethodCode'])) {
            return new JsonResponse(['error' => 'Invalid request body. Expected: {shippingMethodCode, point}'], 400);
        }

        $pointData = $body['point'];
        $pointData['shippingMethodCode'] = $body['shippingMethodCode'];

        $selected = SelectedRelayPoint::fromArray($pointData);
        $this->storage->save($selected, $body['cartToken'] ?? null);

        return new JsonResponse(['success' => true, 'relayPointId' => $selected->id]);
    }

    public function clear(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        $this->storage->clear(is_array($body) ? ($body['cartToken'] ?? null) : null);

        return new JsonResponse(['success' => true]);
    }
}
