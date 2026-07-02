# Sylius Relay Point Plugin

[![CI](https://github.com/BastienMesnil/sylius-relay-point-plugin/actions/workflows/ci.yml/badge.svg)](https://github.com/BastienMesnil/sylius-relay-point-plugin/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

Carrier-agnostic relay point ("point relais") selection for the Sylius 2.x checkout.

Any carrier — French or international — can plug in by implementing a single PHP interface. Geocoding is equally swappable: Addok (French BAN, default, no API key), Nominatim, Google Maps, or Photon.

---

## Features

- **Carrier-agnostic** — implement `RelayPointProviderInterface` to add any carrier without touching the plugin core
- **Geocoding-agnostic** — switch between Addok, Nominatim, Google Maps, Photon, or your own backend via config
- **Built-in providers** — Mondial Relay, Chronopost, Shop2Shop, Colissimo, InPost, DPD, DHL, Packeta, Colis Privé (skeleton)
- **Checkout UX** — Stimulus controller + Leaflet map, embeddable in any Sylius checkout template
- **Session persistence** — selected relay point stored in session, readable server-side during checkout completion

---

## Requirements

- PHP 8.2+
- Sylius 2.0+
- Symfony 7.4+
- `ext-soap` (for SOAP-based carriers: Mondial Relay, Chronopost, Colissimo, Colis Privé)
- `symfony/http-client` (for REST-based providers: InPost, Addok, Nominatim, Google Maps, Photon)

---

## Installation

```bash
composer require keirontw/sylius-relay-point-plugin
```

Register the plugin in `config/bundles.php`:

```php
return [
    // ...
    Keirontw\SyliusRelayPointPlugin\KeirontwSyliusRelayPointPlugin::class => ['all' => true],
];
```

Import plugin routes in `config/routes.yaml`:

```yaml
keirontw_sylius_relay_point_shop:
    resource: "@KeirontwSyliusRelayPointPlugin/config/routes/shop.yaml"
```

---

## Configuration

Create `config/packages/keirontw_sylius_relay_point.yaml`:

```yaml
keirontw_sylius_relay_point:

    # ── Geocoding ────────────────────────────────────────────────────────────
    geocoding:
        # addok: French BAN (free, no key, best for France) — default
        # nominatim: self-hosted OSM (public nominatim.openstreetmap.org forbids SaaS use)
        # google_maps: commercial worldwide
        # photon: self-hosted OSM, lightweight
        # custom: wire your own GeocodingProviderInterface alias in services.yaml
        provider: addok

        addok:
            url: 'https://api-adresse.data.gouv.fr/search/'  # or your self-hosted Addok

        nominatim:
            url: 'https://your-nominatim.example.com/search'
            user_agent: 'MyShop (contact@myshop.com)'
            contact_email: 'contact@myshop.com'
            secret: ~  # optional X-Nominatim-Secret header for self-hosted instances

        google_maps:
            api_key: '%env(GOOGLE_MAPS_API_KEY)%'

        photon:
            url: 'https://your-photon.example.com/api'
            lang: fr

    # ── Carrier providers ────────────────────────────────────────────────────
    providers:

        mondial_relay:
            enabled: true
            account:  '%env(MONDIAL_RELAY_ACCOUNT)%'
            password: '%env(MONDIAL_RELAY_PASSWORD)%'
            shipping_method_codes:
                - mondial_relay_france
                - mondial_relay_belgium

        chronopost:
            enabled: true
            account:  '%env(CHRONOPOST_ACCOUNT)%'
            password: '%env(CHRONOPOST_PASSWORD)%'
            shipping_method_codes:
                - chronopost_pickup_france

        shop2shop:
            enabled: true
            account:  '%env(SHOP2SHOP_ACCOUNT)%'
            password: '%env(SHOP2SHOP_PASSWORD)%'
            shipping_method_codes:
                - shop2shop_france

        colissimo:
            enabled: true
            account_number: '%env(COLISSIMO_ACCOUNT)%'
            password:       '%env(COLISSIMO_PASSWORD)%'
            filter_relay: 'A'  # A=all, P=relay points only, C=lockers only
            shipping_method_codes:
                - colissimo_pickup_france

        inpost:
            enabled: true
            # Country-specific endpoints:
            # France:  https://api.inpost.fr/v1/points
            # Poland:  https://api-pl-points.easypack24.net/v1/points
            # UK:      https://api.inpost.co.uk/v1/points
            base_url: 'https://api.inpost.fr/v1/points'
            shipping_method_codes:
                - inpost_france

        colis_prive:
            enabled: false   # See note below — relay point WSDL pending confirmation
            login:    '%env(COLIS_PRIVE_LOGIN)%'
            password: '%env(COLIS_PRIVE_PASSWORD)%'
            shipping_method_codes:
                - colis_prive_relay

        # ── International (REST, no SOAP) ────────────────────────────────────

        dpd:
            enabled: true
            # API key: https://developer.dpd.com (free, covers all EU countries)
            api_key: '%env(DPD_API_KEY)%'
            shipping_method_codes:
                - dpd_pickup_france
                - dpd_pickup_germany
                - dpd_pickup_belgium

        dhl:
            enabled: true
            # API key: https://developer.dhl.com (free)
            api_key: '%env(DHL_API_KEY)%'
            # parcel:pick-up  → DHL ServicePoints
            # parcel:drop-off-easy → DHL Packstations (Germany)
            service_type: 'parcel:pick-up'
            shipping_method_codes:
                - dhl_servicepoint_france
                - dhl_servicepoint_germany

        packeta:
            enabled: true
            # API key: https://client.packeta.com (free for merchants)
            # Covers: CZ, SK, PL, HU, RO, DE, AT, FR, IT, ES, BE and more
            api_key: '%env(PACKETA_API_KEY)%'
            shipping_method_codes:
                - packeta_czech_republic
                - packeta_slovakia
                - packeta_poland
```

> **Note — Colis Privé relay points:** Colis Privé's label generation API (`WSCP.asmx`) is separate from their relay point search API. The WSDL URL and method name for relay point search must be confirmed with Colis Privé technical support before enabling this provider. The provider skeleton (`ColisPriveRelayProvider`) is ready and only needs the correct endpoint and field mapping.

---

## Checkout integration

### 1. Include the widget in your shipping step template

```twig
{# templates/checkout/shipping.html.twig (or via Sylius Twig Hook) #}

{% include '@KeirontwSyliusRelayPointPlugin/shop/relay_point_picker.html.twig' with {
    searchUrl:       path('keirontw_relay_point_shop_search'),
    geocodeUrl:      path('keirontw_relay_point_shop_geocode'),
    selectUrl:       path('keirontw_relay_point_shop_select'),
    methodCodes:     ['mondial_relay_france', 'chronopost_pickup_france'],
    cartToken:       cart.tokenValue,
    addressStreet:   cart.shippingAddress.street,
    addressCity:     cart.shippingAddress.city,
    addressPostcode: cart.shippingAddress.postcode,
    addressCountry:  cart.shippingAddress.countryCode,
} %}
```

The widget handles:
- Address geocoding via the configured provider
- Parallel search across all `methodCodes`
- Leaflet map with per-carrier colour coding
- Dynamic carrier filter (built from the actual response)
- Opening hours toggle per point

### 2. Register the Stimulus controller

The plugin ships a Stimulus controller (`relay-point-picker`). Add it to your `assets/controllers.json`:

```json
{
    "controllers": [
        {
            "name": "@keirontw/sylius-relay-point-plugin/relay-point-picker",
            "enabled": true,
            "fetch": "eager"
        }
    ]
}
```

Or copy `assets/shop/controllers/relay-point-picker_controller.js` directly into your project's `assets/controllers/` folder and import it manually.

### 3. Handle the selection event

The Stimulus controller dispatches two events that bubble up to the `window`:

| Event | When | `event.detail` |
|---|---|---|
| `relay-point-picker:selected` | User clicks a relay point in the list or map | `{ point }` |
| `relay-point-picker:confirmed` | User clicks "Confirm" | `{ point }` |

When `selectUrl` is provided, the plugin automatically POSTs the selection to the session on confirm. Listen to `relay-point-picker:confirmed` in your own Stimulus controller to trigger the next checkout step:

```js
// assets/controllers/checkout_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.element.addEventListener('relay-point-picker:confirmed', this.onRelayConfirmed.bind(this));
    }

    onRelayConfirmed(event) {
        const { point } = event.detail;
        // e.g. submit the checkout form, update a hidden field, redirect...
        this.element.querySelector('form').submit();
    }
}
```

### 4. Read the selection server-side

During checkout completion (e.g. in an event subscriber on `sylius.order.pre_complete`), inject `RelayPointSessionStorage` and read the selection:

```php
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointSessionStorage;

final class ApplyRelayPointSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RelayPointSessionStorage $storage,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return ['sylius.order.pre_complete' => 'onPreComplete'];
    }

    public function onPreComplete(GenericEvent $event): void
    {
        $order = $event->getSubject();
        $selected = $this->storage->get($order->getTokenValue());

        if (null === $selected) {
            return;
        }

        // Update the order shipping address with relay point data
        $address = $order->getShippingAddress();
        $address->setStreet($selected->street);
        $address->setPostcode($selected->postcode);
        $address->setCity($selected->city);
        $address->setCountryCode($selected->countryCode);
        // Store relay point id for label generation (your own entity field):
        // $address->setRelayPointId($selected->id);

        $this->storage->clear($order->getTokenValue());
    }
}
```

---

## Adding a custom carrier

Implement `RelayPointProviderInterface` and tag the service. That's it — no YAML mapping, no plugin config change needed.

```php
// src/Shipping/DpdProvider.php
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointProviderInterface;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;

final class DpdProvider implements RelayPointProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly array $shippingMethodCodes,
    ) {}

    public function supports(string $shippingMethodCode): bool
    {
        return in_array($shippingMethodCode, $this->shippingMethodCodes, true);
    }

    /** @return RelayPoint[] */
    public function search(RelayPointSearchCriteria $criteria): array
    {
        // Call DPD Pickup REST API and map results to RelayPoint DTOs
        return [];
    }
}
```

Register the service — autoconfiguration applies the tag automatically via `_instanceof`:

```yaml
# config/services.yaml
App\Shipping\DpdProvider:
    arguments:
        $apiKey: '%env(DPD_API_KEY)%'
        $shippingMethodCodes: ['dpd_pickup_france']
```

The provider is immediately discoverable by the plugin registry without any further change.

---

## Adding a custom geocoding provider

Implement `GeocodingProviderInterface`:

```php
use Keirontw\SyliusRelayPointPlugin\Geocoding\GeocodingProviderInterface;
use Keirontw\SyliusRelayPointPlugin\Geocoding\Model\GeocodingResult;

final class MyGeocoder implements GeocodingProviderInterface
{
    public function geocode(string $query): ?GeocodingResult
    {
        // ...
        return new GeocodingResult(latitude: 48.8, longitude: 2.3, postcode: '75001', city: 'Paris', countryCode: 'FR');
    }
}
```

Set `provider: custom` in the plugin config and alias the interface in your services:

```yaml
# config/packages/keirontw_sylius_relay_point.yaml
keirontw_sylius_relay_point:
    geocoding:
        provider: custom

# config/services.yaml
Keirontw\SyliusRelayPointPlugin\Geocoding\GeocodingProviderInterface:
    alias: App\Geocoding\MyGeocoder
```

---

## Licence

MIT. See [LICENSE](LICENSE).
