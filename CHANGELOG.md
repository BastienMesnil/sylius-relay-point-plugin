# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.1.0] — 2026-07-03

### Added
- **Order subscriber** — `RelayPointOrderSubscriber` automatically copies the selected relay point (name, street, postcode, city, country) into the Sylius shipping address on checkout completion and clears the session. Disable with `apply_relay_point_to_order: false`.
- **GLS provider** — GLS ShipIT ParcelShop API (dual mode: geo search via `/distance`, address search via `/address`; Basic auth).
- **PostNL provider** — PostNL Locations API with delivery options filter (`PG` / `PA` / `PG_EX`).
- **bpost provider skeleton** — ready for wiring once bpost business API credentials are available.
- **Twig Hook auto-injection** — the widget inserts itself automatically into `sylius_shop.checkout.select_shipping` via `PrependExtensionInterface`, no template change required.
- **Widget customisation** — 8 named Twig blocks (`relay_styles`, `relay_search_bar`, `relay_filter`, `relay_grid`, `relay_list`, `relay_map`, `relay_selected_summary`, `relay_confirm_button`) for structural overrides via `{% embed %}`. CSS custom properties (`--relay-primary`, `--relay-radius`, …) for theming without Twig changes.
- **npm package** — `@keirontw/sylius-relay-point-plugin` published with Symfony UX `symfony.controllers` metadata for zero-config Stimulus registration.
- **PHP 8.4 support** — added to the CI matrix alongside 8.2 and 8.3.
- **Behat integration tests** — 7 scenarios covering search, geocode, select and clear endpoints using a lightweight kernel (no Sylius store, no database).
- **JS error handling** — loading state, empty-input feedback, `relay-point-picker:error` event, distinct error vs empty-results state, `confirmSelection` blocks on HTTP failure, Leaflet load failure handled.

### Changed
- PHPStan upgraded from v1 to v2 (level 8, no errors).
- `sylius/sylius-rector` removed from dev dependencies (was blocking PHPStan v2).

---

## [1.0.0] — 2026-06-24

### Added
- Carrier-agnostic extension points: `RelayPointProviderInterface`, `RelayPointRegistryInterface`, `RelayPointSearchServiceInterface`.
- Geocoding extension point: `GeocodingProviderInterface` with 4 built-in providers:
  - **Addok** (French BAN — free, no API key, default)
  - **Nominatim** (self-hosted OSM)
  - **Google Maps**
  - **Photon** (self-hosted OSM, lightweight)
- Built-in relay point providers:
  - **Mondial Relay** (SOAP — France, Belgium)
  - **Chronopost Pickup** (SOAP — France)
  - **Shop2Shop** (SOAP — France)
  - **Colissimo** (SOAP — France)
  - **Colis Privé** skeleton (SOAP — pending WSDL confirmation)
  - **InPost** (REST — France, Poland, UK)
  - **DPD** (REST — EU)
  - **DHL** (REST — EU, ServicePoints)
  - **Packeta** (REST — CZ, SK, PL, HU, RO, DE, AT, FR, IT, ES, BE)
- Stimulus controller (`relay-point-picker`) with Leaflet map, per-carrier colour coding, dynamic carrier filter, opening hours toggle, and mobile modal.
- Session persistence via `RelayPointSessionStorage` — scoped per cart token.
- `relay_method_codes()` Twig function — returns all configured relay method codes.
- JSON API endpoints:
  - `GET /{_locale}/relay-points/search`
  - `GET /{_locale}/relay-points/geocode`
  - `POST /{_locale}/relay-points/select`
  - `POST /{_locale}/relay-points/clear`
- PHPUnit unit test suite (29 tests, 75 assertions).
- PHPStan level 8 CI check.

[1.1.0]: https://github.com/BastienMesnil/sylius-relay-point-plugin/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/BastienMesnil/sylius-relay-point-plugin/releases/tag/v1.0.0
