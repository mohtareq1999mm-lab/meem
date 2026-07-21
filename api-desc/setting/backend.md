# Settings Module — Backend Architecture (Admin API)

## Endpoints

| Method | URL | Auth | Permission | Purpose |
|--------|-----|------|------------|---------|
| GET | `/api/v1/settings` | Public | — | Fetch platform settings |
| PUT | `/api/v1/settings` | Sanctum | `update-settings` | Update platform settings |
| GET | `/api/v1/fast-shipping/settings` | Sanctum | `view-fast-shipping` | Fetch fast shipping settings |
| PUT | `/api/v1/fast-shipping/settings` | Sanctum | `update-fast-shipping` | Update fast shipping settings |

## Route Definitions

**File:** `packages/marvel/src/Rest/Routes.php`

```php
Route::get('settings', [SettingsController::class, 'index']);             // line 134
Route::put('settings', [SettingsController::class, 'update']);            // line 243
Route::get('fast-shipping/settings', [FastShippingController::class, 'getSettings']);    // line 150, 244
Route::put('fast-shipping/settings', [FastShippingController::class, 'updateSettings']); // line 151, 245

// Also registered via apiResource (index only):
Route::apiResource('settings', SettingsController::class, ['only' => ['index']]); // line 390
```

## Middleware

| Endpoint | Middleware |
|----------|-----------|
| GET /settings | `api` group — no auth |
| PUT /settings | `auth:sanctum`, `permission:update-settings` |
| GET /fast-shipping/settings | `auth:sanctum`, `permission:view-fast-shipping` |
| PUT /fast-shipping/settings | `auth:sanctum`, `permission:update-fast-shipping` |

## Key Classes

| Class | Method | Responsibility |
|-------|--------|----------------|
| `SettingsController` | `index()` | Return settings singleton via SettingResource |
| `SettingsController` | `update()` | Update settings fields via SettingsRequest |
| `FastShippingController` | `getSettings()` | Return fast shipping config from `options.fast_shipping` |
| `FastShippingController` | `updateSettings()` | Validate + merge into `options.fast_shipping`, clear cache |
| `FastShippingRepository` | `getSettings()` | Read from cache/DB, default values |
| `FastShippingRepository` | `updateSettings()` | Merge into options, lockForUpdate transaction, clear cache |
| `SettingResource` | `toArray()` | Transform settings for response |

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
| GET /settings | No cache |
| GET /fast-shipping/settings | `Cache::remember('fast_shipping_settings', 3600s)` |
| PUT /fast-shipping/settings | Clears `fast_shipping_settings` cache key |

The fast shipping update uses `lockForUpdate()` transaction to prevent race conditions.

## minimumOrderAmount

| Field | Source | Default |
|-------|--------|---------|
| `minimumOrderAmount` | `settings.options.minimumOrderAmount` | 0 |
| Top-level in response | `SettingResource` extracts via `$this->options['minimumOrderAmount'] ?? 0` |
| Enforced in | `CheckoutRepository::verify()` — throws 400 if cart total < minimum |
