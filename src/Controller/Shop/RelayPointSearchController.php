<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Controller\Shop;

use Keirontw\SyliusRelayPointPlugin\Geocoding\GeocodingProviderInterface;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointSearchServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class RelayPointSearchController extends AbstractController
{
    public function __construct(
        private readonly RelayPointSearchServiceInterface $searchService,
        private readonly GeocodingProviderInterface $geocodingProvider,
    ) {
    }

    /**
     * Returns relay points for a given shipping method code.
     *
     * GET /relay-points/search
     *   ?shipping_method_code=mondial_relay_fr  (required)
     *   &postcode=75001
     *   &city=Paris
     *   &country_code=FR
     *   &latitude=48.8566
     *   &longitude=2.3522
     *   &limit=10
     *   &radius=20000  (in metres)
     */
    public function search(Request $request): JsonResponse
    {
        $shippingMethodCode = $request->query->getString('shipping_method_code');

        if ('' === $shippingMethodCode) {
            return new JsonResponse(['error' => 'Missing parameter: shipping_method_code'], 400);
        }

        $latitudeRaw = $request->query->getString('latitude');
        $longitudeRaw = $request->query->getString('longitude');
        $radiusRaw = $request->query->getString('radius');

        $criteria = new RelayPointSearchCriteria(
            postcode: $request->query->getString('postcode') ?: null,
            city: $request->query->getString('city') ?: null,
            countryCode: $request->query->getString('country_code') ?: 'FR',
            latitude: '' !== $latitudeRaw ? (float) $latitudeRaw : null,
            longitude: '' !== $longitudeRaw ? (float) $longitudeRaw : null,
            limit: $request->query->getInt('limit', 10),
            radiusInMeters: '' !== $radiusRaw ? (int) $radiusRaw : null,
        );

        $points = $this->searchService->searchByShippingMethod($shippingMethodCode, $criteria);

        return new JsonResponse(array_map(
            static fn (RelayPoint $p) => [
                'id' => $p->id,
                'shippingMethodCode' => $shippingMethodCode,
                'name' => $p->name,
                'street' => $p->street,
                'postcode' => $p->postcode,
                'city' => $p->city,
                'countryCode' => $p->countryCode,
                'latitude' => $p->latitude,
                'longitude' => $p->longitude,
                'distanceInMeters' => $p->distanceInMeters,
                'openingHours' => array_map(
                    static fn ($oh) => ['day' => $oh->day, 'hours' => $oh->hours],
                    $p->openingHours,
                ),
                'carrierCode' => $p->carrierCode,
            ],
            $points,
        ));
    }

    /**
     * Geocodes a free-text address using the configured GeocodingProviderInterface.
     *
     * GET /relay-points/geocode?q=10+rue+de+la+Paix+75001+Paris
     */
    public function geocode(Request $request): JsonResponse
    {
        $query = $request->query->getString('q');

        if ('' === $query) {
            return new JsonResponse(['error' => 'Missing parameter: q'], 400);
        }

        $result = $this->geocodingProvider->geocode($query);

        if (null === $result) {
            return new JsonResponse(null, 204);
        }

        return new JsonResponse([
            'latitude' => $result->latitude,
            'longitude' => $result->longitude,
            'postcode' => $result->postcode,
            'city' => $result->city,
            'countryCode' => $result->countryCode,
        ]);
    }
}
