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
| Routes | `packages/marvel/src/Rest/Routes.php` (lines 134, 150-151, 243-245, 390) |
| Request | `packages/marvel/src/Http/Requests/SettingsRequest.php` |

## Routes

| Method | Endpoint | Auth | Permission | Purpose |
|--------|----------|------|------------|---------|
| GET | `/api/v1/settings` | Public | — | Fetch platform settings |
| PUT | `/api/v1/settings` | Sanctum | `update-settings` | Update platform settings |
| GET | `/api/v1/fast-shipping/settings` | Sanctum | `view-fast-shipping` | Fetch fast shipping config |
| PUT | `/api/v1/fast-shipping/settings` | Sanctum | `update-fast-shipping` | Update fast shipping config |

## Dependencies

- **Spatie Translatable** (`HasTranslations`) — bilingual site_name, site_desc, meta_desc, copyright
- **Spatie Media Library** (`InteractsWithMedia`) — logo, favicon
- **SettingResource** — response transformation
- **Cache** — fast shipping settings cached for 1 hour
- **LockForUpdate** — prevents race conditions on settings update
