<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Geocoding;

use Keirontw\SyliusRelayPointPlugin\Geocoding\Model\GeocodingResult;

/**
 * Implement this interface to plug a geocoding backend (a self-hosted
 * Nominatim instance, Photon, a paid API...) into the address search used
 * to center the relay point map. The default implementation shipped with
 * this plugin targets a self-hosted Nominatim instance.
 */
interface GeocodingProviderInterface
{
    public function geocode(string $query): ?GeocodingResult;
}
