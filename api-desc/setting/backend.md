# Settings Module — Backend Architecture (Admin API)

## Endpoints

| Method | URL | Auth | Permission | Purpose |
|--------|-----|------|------------|---------|
| GET | `/api/v1/settings` | Sanctum | `view-settings` | Fetch platform settings |
| PUT | `/api/v1/settings` | Sanctum | `update-settings` | Update platform settings |
| GET | `/api/v1/fast-shipping/settings` | Sanctum | `view-fast-shipping` | Fetch fast shipping settings |
| PUT | `/api/v1/fast-shipping/settings` | Sanctum | `update-fast-shipping` | Update fast shipping settings |
| GET | `/api/v1/general/settings` | Public | — | Public settings (`settings.front`) |

## Route Definitions

**File:** `packages/marvel/src/Rest/Routes.php`

```php
// inside Route::middleware(['auth:sanctum', 'throttle:admin'])->group():
Route::get('settings', [SettingsController::class, 'index']);             // line 114
Route::put('settings', [SettingsController::class, 'update']);            // line 115
Route::get('fast-shipping/settings', [FastShippingController::class, 'getSettings']);    // line 117
Route::put('fast-shipping/settings', [FastShippingController::class, 'updateSettings']); // line 118
```

The public settings endpoint is in `routes/api.php` under the `v1/general` prefix:
```php
Route::get('settings', [SettingController::class, 'index'])->name('settings.front'); // routes/api.php:80
```

## Middleware

| Endpoint | Middleware |
|----------|-----------|
| GET /settings | `auth:sanctum`, `permission:view-settings` (via `SettingsController` constructor) |
| PUT /settings | `auth:sanctum`, `permission:update-settings` |
| GET /fast-shipping/settings | `auth:sanctum`, `permission:view-fast-shipping` |
| PUT /fast-shipping/settings | `auth:sanctum`, `permission:update-fast-shipping` |

## Key Classes

| Class | Method | Responsibility |
|-------|--------|----------------|
| `SettingsController` | `index()` | Return settings singleton via SettingResource (cached under `settings` tag via `HasCache::remember`) |
| `SettingsController` | `update()` | Update settings fields via SettingsRequest; merges `currency_selection_enabled` into options, resets effective-currency memo, flushes settings tag |
| `FastShippingController` | `getSettings()` | Return fast shipping config from `options.fast_shipping` |
| `FastShippingController` | `updateSettings()` | Validate + merge into `options.fast_shipping`, clear cache |
| `FastShippingRepository` | `getSettings()` | Read from cache/DB, default values |
| `FastShippingRepository` | `updateSettings()` | Merge into options, lockForUpdate transaction, clear cache |
| `SettingResource` | `toArray()` | Transform settings for response (incl. top-level `currency_selection_enabled`) |

## Fast Shipping Data Flow

```
settings.options.fast_shipping
  ├── enabled            (bool, default false)
  ├── duration_minutes   (int,  default 120)
  ├── fee                (float, default 0)
  ├── start_hour         (string "H:i", default "08:00")
  └── end_hour           (string "H:i", default "22:00")
```

All stored inside the `settings` table `options` JSON column under the `fast_shipping` key.

## Caching

| Endpoint | Cache |
|----------|-------|
| GET /settings | Cached under `settings` tag (`HasCache::remember('settings', md5(fullUrl), ...)`, TTL 4h) |
| GET /fast-shipping/settings | `Cache::remember('fast_shipping_settings', 3600s)` |
| PUT /settings | Flushes the `settings` tag (`flushTag(FrontendResource::SETTINGS->value)`) + resets `CurrencyService` effective-currency memo when `currency_selection_enabled` is present |
| PUT /fast-shipping/settings | Clears `fast_shipping_settings` cache key |

The fast shipping update uses `lockForUpdate()` transaction to prevent race conditions.

## minimumOrderAmount

| Field | Source | Default |
|-------|--------|---------|
| `minimumOrderAmount` | `settings.options.minimumOrderAmount` | 0 |
| Top-level in response | `SettingResource` extracts via `$this->options['minimumOrderAmount'] ?? 0` |
| Enforced in | `CheckoutRepository::verify()` — throws 400 if cart total < minimum |

## currency_selection_enabled

| Field | Source | Default |
|-------|--------|---------|
| Top-level in response | `SettingResource`: `(bool) data_get($this->options, 'currency_selection_enabled', false)` |
| Stored in | `settings.options.currency_selection_enabled` (JSON boolean) |
| Seeder default | `SettingSeeder` sets `??= false` |
| Written via | `PUT /api/v1/settings` with `currency_selection_enabled` (`sometimes|boolean`) — merged into options |
| Consumed by | `App\Services\Currency\CurrencyService::isCurrencySelectionEnabled()` / `getEffectiveCode()` |

When `true`, the storefront can honor a customer-selected currency (user preference or guest cookie). When `false` (default), the effective currency always resolves to the catalog code.
