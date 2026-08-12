# Settings Module — Admin API

## Overview

The Settings Admin module provides CRUD for platform-wide configuration: site info, SEO, social links, contact, media, and JSON options. Fast shipping settings are stored inside `options.fast_shipping` and managed via separate endpoints with caching.

## Key Files

| Layer | File |
|-------|------|
| Controller | `packages/marvel/src/Http/Controllers/SettingsController.php` |
| Controller | `packages/marvel/src/Http/Controllers/FastShippingController.php` |
| Repository | `packages/marvel/src/Database/Repositories/FastShippingRepository.php` |
| Resource | `Marvel\Http\Resources\SettingResource.php` |
| Model | `Marvel\Database\Models\Settings.php` |
| Routes | `packages/marvel/src/Rest/Routes.php` (lines 114-118) |
| Request | `packages/marvel/src/Http/Requests/SettingsRequest.php` |
| Currency Service | `app/Services/Currency/CurrencyService.php` (effective-currency gating via `currency_selection_enabled`) |
| Seeder | `database/seeders/SettingSeeder.php` (options defaults) |

## Routes

| Method | Endpoint | Auth | Permission | Purpose |
|--------|----------|------|------------|---------|
| GET | `/api/v1/settings` | Sanctum | `view-settings` | Fetch platform settings (admin) |
| PUT | `/api/v1/settings` | Sanctum | `update-settings` | Update platform settings |
| GET | `/api/v1/fast-shipping/settings` | Sanctum | `view-fast-shipping` | Fetch fast shipping config |
| PUT | `/api/v1/fast-shipping/settings` | Sanctum | `update-fast-shipping` | Update fast shipping config |

> **Note:** `GET /api/v1/settings` is **not** public — it sits inside the `auth:sanctum` group. The public (unauthenticated) settings endpoint is `GET /api/v1/general/settings` (`settings.front`), which returns the same `SettingResource` shape with translatable fields resolved to the request locale.

## Options Keys

Platform JSON `options` includes (among others):

- `minimumOrderAmount` — top-level `minimumOrderAmount` in the response
- `fast_shipping` — managed via the fast-shipping endpoints
- `currency_selection_enabled` — boolean (default `false`); exposed as top-level `currency_selection_enabled`. When `false`, the storefront effective currency always resolves to the catalog code; when `true`, customer-selected currency preference / guest cookie is honored.

## Dependencies

- **Spatie Translatable** (`HasTranslations`) — bilingual site_name, site_desc, meta_desc, copyright
- **Spatie Media Library** (`InteractsWithMedia`) — logo, favicon
- **SettingResource** — response transformation
- **Cache** — fast shipping settings cached for 1 hour
- **LockForUpdate** — prevents race conditions on settings update
