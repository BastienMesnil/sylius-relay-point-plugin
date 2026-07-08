# Sylius Relay Point Plugin

[![CI](https://github.com/BastienMesnil/sylius-relay-point-plugin/actions/workflows/ci.yml/badge.svg?branch=1.x)](https://github.com/BastienMesnil/sylius-relay-point-plugin/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://www.php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

> **You are on the `1.x` branch — Sylius 1.9+ / Symfony 5.4–6.x.**
> For Sylius 2.x see the [`main` branch](https://github.com/BastienMesnil/sylius-relay-point-plugin/tree/main).

Carrier-agnostic relay point ("point relais") selection for the Sylius 1.9+ checkout.

Any carrier — French or international — plugs in by implementing a single PHP interface. Geocoding is equally swappable: Addok (French BAN, default, no API key), Nominatim, Google Maps, or Photon.

---

## Screenshots

| List + map | Point selected | Checkout context |
|:---:|:---:|:---:|
| ![Widget list and map view](docs/screenshots/widget-list-map.png) | ![Point selected with opening hours](docs/screenshots/point-selected.png) | ![Full checkout page with widget](docs/screenshots/checkout-context.png) |

---

## Features

- **Carrier-agnostic** — implement `RelayPointProviderInterface` to add any carrier without touching the plugin core
- **Geocoding-agnostic** — switch between Addok, Nominatim, Google Maps, Photon, or your own backend via config
- **Built-in providers** — Mondial Relay, Chronopost, Shop2Shop, Colissimo, InPost, GLS, DPD, DHL, Packeta, PostNL, bpost (skeleton), Colis Privé (skeleton)
- **Auto-injected checkout block** — appears automatically in `sylius.shop.checkout.select_shipping.before_navigation` via `SyliusUiBundle`, no template edit required
- **Checkout UX** — Stimulus controller + Leaflet map, embeddable in any Sylius 1.x checkout template
- **Session persistence** — selected relay point stored in session, readable server-side during checkout completion
- **Built-in order subscriber** — optional subscriber copies the relay point into the Sylius shipping address on `sylius.order.pre_complete`

---

## Requirements

- PHP 8.1+
- Sylius 1.9+
- Symfony 5.4 or 6.x
- `ext-soap` (for SOAP-based carriers: Mondial Relay, Chronopost, Colissimo, Colis Privé)
- `symfony/http-client` (for REST-based providers: InPost, GLS, DPD, DHL, Packeta, PostNL)

---

## Installation

```bash
composer require keirontw/sylius-relay-point-plugin:"^1.0"
```

Register the plugin in `config/bundles.php`:

```php
return [
    // ...
    Keirontw\SyliusRelayPointPlugin\KeirontwSyliusRelayPointPlugin::class => ['all' => true],
];
```

Import plugin routes in `config/routes/keirontw_relay_point.yaml`:

```yaml
keirontw_relay_point_shop:
    resource: "@KeirontwSyliusRelayPointPlugin/config/routes/shop.yaml"
```

---

## Configuration

Create `config/packages/keirontw_sylius_relay_point.yaml`:

```yaml
keirontw_sylius_relay_point:

    # ── Widget UI ────────────────────────────────────────────────────────────
    # CSS framework used to render the relay point picker widget.
    #   tailwind  — default
    #   bootstrap — pick this if your Sylius 1.x shop theme is on Bootstrap 4/5
    # See "Customising the widget → CSS framework" below for details.
    ui:
        theme: tailwind

    # ── Geocoding ─────────────────────────────────────────────────────────────
    geocoding:
        # addok        — French BAN (free, no key, best for France) — default
        # nominatim    — self-hosted OSM (public nominatim.openstreetmap.org forbids SaaS use)
        # google_maps  — commercial worldwide
        # photon       — self-hosted OSM, lightweight
        # custom       — wire your own GeocodingProviderInterface alias in services.yaml
        provider: addok

        addok:
            url: 'https://api-adresse.data.gouv.fr/search/'   # or your self-hosted Addok

        nominatim:
            url: 'https://your-nominatim.example.com/search'
            user_agent: 'MyShop (contact@myshop.com)'
            contact_email: 'contact@myshop.com'

        google_maps:
            api_key: '%env(GOOGLE_MAPS_API_KEY)%'

        photon:
            url: 'https://your-photon.example.com/api'
            lang: fr

    # ── Order subscriber ──────────────────────────────────────────────────────
    # When true, the plugin automatically copies the relay point (street, postcode,
    # city, countryCode, company name) into the Sylius shipping address upon
    # checkout completion and clears the session entry.
    apply_relay_point_to_order: true   # default: true

    # ── Carrier providers ─────────────────────────────────────────────────────
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
            filter_relay: 'A'   # A=all, P=relay points only, C=lockers only
            shipping_method_codes:
                - colissimo_pickup_france

        inpost:
            enabled: true
            base_url: 'https://api.inpost.fr/v1/points'
            shipping_method_codes:
                - inpost_france

        dpd:
            enabled: true
            api_key: '%env(DPD_API_KEY)%'
            shipping_method_codes:
                - dpd_pickup_france

        dhl:
            enabled: true
            api_key: '%env(DHL_API_KEY)%'
            service_type: 'parcel:pick-up'
            shipping_method_codes:
                - dhl_servicepoint_france

        packeta:
            enabled: true
            api_key: '%env(PACKETA_API_KEY)%'
            shipping_method_codes:
                - packeta_czech_republic

        post_nl:
            enabled: true
            api_key: '%env(POSTNL_API_KEY)%'
            delivery_options: 'PG'
            shipping_method_codes:
                - postnl_netherlands

        gls:
            enabled: true
            username: '%env(GLS_USERNAME)%'
            password: '%env(GLS_PASSWORD)%'
            shipping_method_codes:
                - gls_france

        bpost:
            enabled: false
            api_key:  '%env(BPOST_API_KEY)%'
            base_url: '%env(BPOST_API_BASE_URL)%'
            shipping_method_codes:
                - bpost_belgium

        colis_prive:
            enabled: false
            login:    '%env(COLIS_PRIVE_LOGIN)%'
            password: '%env(COLIS_PRIVE_PASSWORD)%'
            shipping_method_codes:
                - colis_prive_relay
```

> **bpost:** bpost does not expose a public API for parcel point search. Access requires a verified business partner agreement. The `BpostProvider` skeleton is ready.

> **Colis Privé:** the relay point search WSDL URL must be confirmed with Colis Privé technical support. The `ColisPriveRelayProvider` skeleton is ready.

---

## Checkout integration

### Automatic template event (recommended)

The plugin auto-registers itself in the `sylius.shop.checkout.select_shipping.before_navigation` event via `PrependExtensionInterface`. No template changes are required — the widget appears automatically when the customer selects a shipping method whose code is listed in `shipping_method_codes`.

The block is injected with priority 10. Override if needed:

```yaml
# config/packages/sylius_ui.yaml
sylius_ui:
    events:
        sylius.shop.checkout.select_shipping.before_navigation:
            blocks:
                relay_point_picker:
                    priority: 100
```

> **Prerequisite:** your `selectShipping.html.twig` must call `sylius_template_event('sylius.shop.checkout.select_shipping.before_navigation', {'order': order})`. This is present in the default Sylius shop template. If your project overrides this template, add the call manually (see Manual embed below).

### Manual embed (alternative)

For custom checkout flows that do not use the default Sylius template event:

```twig
{% set relay_codes = relay_method_codes() %}
{% for shipment in order.shipments %}
    {% if shipment.method is not null and shipment.method.code in relay_codes %}
        {% include '@KeirontwSyliusRelayPointPlugin/shop/relay_point_picker.html.twig' with {
            searchUrl:       path('keirontw_relay_point_shop_search'),
            geocodeUrl:      path('keirontw_relay_point_shop_geocode'),
            selectUrl:       path('keirontw_relay_point_shop_select'),
            methodCodes:     [shipment.method.code],
            cartToken:       order.tokenValue,
            addressStreet:   order.shippingAddress ? order.shippingAddress.street : '',
            addressCity:     order.shippingAddress ? order.shippingAddress.city : '',
            addressPostcode: order.shippingAddress ? order.shippingAddress.postcode : '',
            addressCountry:  order.shippingAddress ? order.shippingAddress.countryCode : '',
        } %}
    {% endif %}
{% endfor %}
```

### Register the Stimulus controller

Install the npm package:

```bash
npm install @keirontw/sylius-relay-point-plugin
```

Add the entry to your `assets/controllers.json`:

```json
{
    "controllers": {
        "@keirontw/sylius-relay-point-plugin": {
            "relay-point-picker": {
                "enabled": true,
                "fetch": "eager"
            }
        }
    }
}
```

**Without npm:** copy `assets/shop/controllers/relay-point-picker_controller.js` into your project's `assets/controllers/` and import it in your entrypoint.

### Widget events

The Stimulus controller dispatches events that bubble to `window`:

| Event | When | `event.detail` |
|---|---|---|
| `relay-point-picker:selected` | Customer clicks a point in the list or map | `{ point }` |
| `relay-point-picker:confirmed` | Customer clicks "Confirm" | `{ point }` |
| `relay-point-picker:error` | Network or API error | `{ message }` |

Listen to `relay-point-picker:confirmed` to trigger the next checkout step:

```js
this.element.addEventListener('relay-point-picker:confirmed', () => {
    this.element.querySelector('form').submit();
});
```

### Order completion — built-in subscriber

When `apply_relay_point_to_order: true` (the default), the plugin registers `RelayPointOrderSubscriber` which listens on `sylius.order.pre_complete` (winzou state machine event) and:

1. Reads `RelayPointSessionStorageInterface::get($order->getTokenValue())`
2. If a relay point is found, updates the shipping address (street, postcode, city, countryCode, company = relay point name)
3. Clears the session entry

To **disable** it:

```yaml
keirontw_sylius_relay_point:
    apply_relay_point_to_order: false
```

To **extend** the behaviour (e.g. store the relay point ID on a custom entity field), disable the built-in subscriber and write your own:

```php
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointSessionStorageInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'sylius.order.pre_complete')]
final class ApplyRelayPointSubscriber
{
    public function __construct(
        private readonly RelayPointSessionStorageInterface $storage,
    ) {}

    public function __invoke(ResourceControllerEvent $event): void
    {
        $order = $event->getSubject();
        $point = $this->storage->get($order->getTokenValue());

        if (null === $point) {
            return;
        }

        $address = $order->getShippingAddress();
        $address->setStreet($point->street);
        $address->setPostcode($point->postcode);
        $address->setCity($point->city);
        $address->setCountryCode($point->countryCode);
        $address->setCompany($point->name);

        // Your custom field:
        $address->setRelayPointId($point->id);

        $this->storage->clear($order->getTokenValue());
    }
}
```

---

## Customising the widget

### CSS framework (Tailwind or Bootstrap)

The widget ships with a Tailwind-based layout by default, but the classes are not hardcoded in the template: every element resolves its class list through the `relay_ui_class()` Twig function, backed by `Keirontw\SyliusRelayPointPlugin\Ui\RelayPointUiClasses`. If your Sylius 1.x shop theme is on Bootstrap, switch the theme in config — no template change needed:

```yaml
# config/packages/keirontw_sylius_relay_point.yaml
keirontw_sylius_relay_point:
    ui:
        theme: bootstrap   # tailwind (default) | bootstrap
```

This also covers the HTML the widget generates client-side (list items, the Leaflet popup, the carrier filter, opening hours). The Stimulus controller receives the active theme via `data-relay-point-picker-theme-value` and resolves the same semantic keys against its own `UI_CLASSES` map (`assets/shop/controllers/relay-point-picker_controller.js`) — the two class maps are kept manually in sync, so if you add a new element to the template, mirror the key in both `RelayPointUiClasses` and `UI_CLASSES`.

CSS variables (below) remain the recommended way to adjust colors/radius on either theme — you don't need to touch the class maps for a simple palette change.

### CSS variables (theming — no Twig change needed)

```css
.relay-picker {
    --relay-primary:        #7c3aed;
    --relay-primary-hover:  #6d28d9;
    --relay-primary-bg:     #f5f3ff;
    --relay-primary-border: #ddd6fe;
    --relay-radius:         0.375rem;
    --relay-border:         #d1d5db;
}
```

### Twig blocks (structural overrides — via `{% embed %}`)

```twig
{% embed '@KeirontwSyliusRelayPointPlugin/shop/relay_point_picker.html.twig' with {
    searchUrl:   path('keirontw_relay_point_shop_search'),
    geocodeUrl:  path('keirontw_relay_point_shop_geocode'),
    selectUrl:   path('keirontw_relay_point_shop_select'),
    methodCodes: relay_method_codes(),
    cartToken:   order.tokenValue,
} %}
    {% block relay_confirm_button %}
        <button type="button"
            data-action="click->relay-point-picker#confirmSelection"
            class="btn btn-primary w-full">
            Valider ce point relais
        </button>
    {% endblock %}
{% endembed %}
```

Available blocks: `relay_styles`, `relay_search_bar`, `relay_filter`, `relay_grid`, `relay_list`, `relay_map`, `relay_selected_summary`, `relay_confirm_button`.

---

## Adding a custom carrier

Implement `RelayPointProviderInterface` and tag the service:

```php
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointProviderInterface;

final class MyCarrierProvider implements RelayPointProviderInterface
{
    public function supports(string $shippingMethodCode): bool
    {
        return $shippingMethodCode === 'my_carrier';
    }

    public function search(RelayPointSearchCriteria $criteria): array
    {
        return []; // map your API response to RelayPoint DTOs
    }
}
```

```yaml
# config/services.yaml
App\Shipping\MyCarrierProvider:
    tags: [keirontw_sylius_relay_point.relay_point_provider]
```

---

## Troubleshooting

**Widget does not appear at checkout**
- Verify the shipping method code is listed in `shipping_method_codes` for at least one enabled provider.
- If using the automatic injection, confirm your `selectShipping.html.twig` calls `sylius_template_event('sylius.shop.checkout.select_shipping.before_navigation', {'order': order})`.
- Run `bin/console debug:config sylius_ui` and look for the `relay_point_picker` block under the event.
- Call `{{ relay_method_codes() }}` in a template to dump all registered codes.

**Geocode returns no result**
- Addok only covers French addresses. Switch to Nominatim or Google Maps for international addresses.

**Search returns an empty list**
- Check credentials (log channel `keirontw_relay_point`, level `error`).
- Verify the country code format matches what the provider expects.

**Relay point not saved to the order**
- Make sure `apply_relay_point_to_order: true` (default).
- Confirm `selectUrl` is passed to the widget and the POST returns `{"success":true}`.
- The subscriber reads the session via cart token: `cartToken` must match `order.tokenValue`.

---

## Licence

MIT. See [LICENSE](LICENSE).
