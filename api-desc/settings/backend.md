# Settings Module — Backend Architecture

## Overview

The Settings module manages platform-wide configuration (site name, SEO, contact info, media, maintenance mode, etc.). It follows a singleton pattern — there is conceptually one settings record for the entire platform.

## Endpoints

| Method | URL | Auth | Permission | Purpose |
|--------|-----|------|------------|---------|
| GET | `/api/v1/settings` | Public | None (commented out: `view-settings`) | Fetch platform settings |
| PUT | `/api/v1/settings` | `auth:sanctum` + `update-settings` | Update via permission middleware | Update platform settings |
| PUT | `/api/v1/settings/{setting}` | `auth:sanctum` + `verified` + `update-settings` | Super Admin group | Update platform settings (parameter ignored) |
| GET | `/api/v1/general/settings` | Public | None | Public settings via `SettingController` (app-level) |

## Route Definitions

**File:** `packages/marvel/src/Rest/Routes.php`

```
Line 134: Route::get('settings', [SettingsController::class, 'index']);       // Public
Line 135: Route::put('settings', [SettingsController::class, 'update']);       // Permission-protected via controller constructor
Line 244: Route::apiResource('settings', SettingsController::class, ['only' => ['index']]);  // Duplicate GET (first wins)
Line 694: Route::apiResource('settings', SettingsController::class, ['only' => ['update']]); // Auth+verified+permission
```

**Public (app-level):**
`routes/api.php:65` — `Route::get('settings', [SettingController::class, 'index']);`

## Middleware

- **GET** (line 134): No middleware. Controller constructor has `VIEW_SETTINGS` permission middleware but it's **commented out**.
- **PUT** (line 135): Controller constructor has `UPDATE_SETTINGS` permission middleware (requires auth via permission check).
- **PUT** (line 694): `auth:sanctum` + `verified` + constructor `UPDATE_SETTINGS` permission.

## Controller Flow

### SettingsController (`packages/marvel/src/Http/Controllers/SettingsController.php`)

```
GET /settings
  → SettingsController@index
    → SettingsRepository::getApplicationSettings()
      → SettingsRepository::first()
    → SettingResource::make($settings)
    → $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ...)

PUT /settings
  → SettingsController@update($id = null)
    → SettingsRepository::first()
    → if $settings exists:
      → SettingsRepository::updateSetting($request, $settings->id)
    → SettingResource::make($settings)
    → $this->apiResponse(SETTINGS_UPDATED_SUCCESSFULLY, 200, true, ...)
```

### SettingController (Public — `app/Http/Controllers/Api/General/SettingController.php`)

```
GET /general/settings
  → SettingController@index
    → SettingService::getSetting()
      → Settings::first()
    → SettingResource::make($setting)
    → $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ...)
```

## Repository: SettingsRepository

**File:** `packages/marvel/src/Database/Repositories/SettingsRepository.php`

| Method | Description |
|--------|-------------|
| `model()` | Returns `Settings::class` |
| `getApplicationSettings()` | Alias for `getAppSettingsData()` → `$this->first()` |
| `updateSetting($data, $id)` | Transactional update with image handling |

### `updateSetting()` Flow

```
1. DB::beginTransaction()
2. $setting = $this->first()
3. $setting->update($data->except('logo', 'favicon'))
4. If logo: updateSingleImage($data, 'logo', $setting, 'logo-setting', 'settings')
5. If favicon: updateSingleImage($data, 'favicon', $setting, 'favicon-setting', 'settings')
6. DB::commit()
7. Return $setting

On error:
  - HttpException(422): Logo/favicon upload failed
  - HttpException(500): Generic failure (rollback)
```

## Model: Settings

**File:** `packages/marvel/src/Database/Models/Settings.php`

| Property | Details |
|----------|---------|
| Table | `settings` |
| Translatable | `site_name`, `site_desc`, `meta_desc`, `site_copy_right` |
| Fillable | `site_name`, `site_desc`, `meta_desc`, `site_copy_right`, `logo`, `favicon`, `site_email`, `email_support`, `facebook`, `instagram`, `linkedin`, `promotion_video_url`, `youtube`, `phone`, `fast_shipping_page_publish`, `options` |
| Casts | `options => array` |
| Traits | `HasTranslations`, `InteractsWithMedia` |
| Static Methods | `getData($language = null)` — cached singleton retrieval |

### `getData()` Static Method

```php
public static function getData($language = null)
{
    $language = $language ?? DEFAULT_LANGUAGE;
    return Cache::remember('cached_settings_' . $language, 86400, function () {
        return static::first();
    });
}
```

Called from 19 locations across the codebase. Uses cache key `cached_settings_{language}` with 24-hour TTL.

## Resource: SettingResource

**File:** `packages/marvel/src/Http/Resources/SettingResource.php`

Returns:
```json
{
  "site_name": "translated string",
  "site_desc": "translated string",
  "meta_desc": "translated string",
  "site_copy_right": "translated string",
  "logo": "url from media library",
  "favicon": "url from media library",
  "site_email": "string",
  "email_support": "string",
  "facebook": "url",
  "instagram": "url",
  "linkedin": "url",
  "promotion_video_url": "url|null",
  "youtube": "url",
  "phone": "string",
  "fast_shipping_page_publish": "boolean|int",
  "options": "object|null"
}
```

## Request Validation: SettingsRequest

**File:** `packages/marvel/src/Http/Requests/SettingsRequest.php`

| Field | Rules |
|-------|-------|
| `site_name` | `required`, `array`, each: `required`, `string`, `min:3`, `max:200` |
| `site_desc` | `required`, `array`, each: `required`, `string`, `min:3`, `max:2000` |
| `meta_desc` | `required`, `array`, each: `required`, `string`, `min:3`, `max:2000` |
| `site_copy_right` | `required`, `array`, each: `required`, `string`, `min:3`, `max:200` |
| `logo` | `required`, `image`, `mimes:jpeg,png,jpg,gif,svg`, `max:2048` |
| `favicon` | `required`, `image`, `mimes:jpeg,png,jpg,gif,svg`, `max:2048` |
| `site_email` | `required`, `email` |
| `email_support` | `required`, `email` |
| `facebook` | `required`, `url` |
| `instagram` | `required`, `url` |
| `linkedin` | `required`, `url` |
| `promotion_video_url` | `sometimes`, `url` |
| `youtube` | `required`, `url` |
| `phone` | `required`, `string` |
| `fast_shipping_page_publish` | `required`, `in:0,1` |
| `options` | `sometimes`, `array` |

## Events & Listeners

### Maintenance Event

Dispatched when settings are updated via `store()`.

```
SettingsController@store
  → event(new Maintenance($language))
    → MaintenanceNotification listener
      → Settings::getData($language)
      → If isUnderMaintenance && currentTime < startTime:
        → Notify SUPER_ADMIN + STORE_OWNER users via email
```

**Files:**
- `packages/marvel/src/Events/Maintenance.php`
- `packages/marvel/src/Listeners/MaintenanceNotification.php`
- `packages/marvel/src/Notifications/MaintenanceReminder.php`

## Media Handling

Trait: `Marvel\Traits\MediaManager`

Used for logo and favicon uploads:
- Collection: `logo-setting` → disk: `settings`
- Collection: `favicon-setting` → disk: `settings`

Methods:
- `updateSingleImage($request, $nameInput, $model, $collectionName, $disk)`: Clears collection then adds new media.

## Cache

- Key pattern: `cached_settings_{language}` (e.g., `cached_settings_en`)
- TTL: 86400 seconds (24 hours)
- Cache is cleared in `store()` when existing settings are updated
- `getData()` uses `Cache::remember()` for read-through caching
- Cache invalidation happens via `Cache::forget()` in `store()` and is NOT triggered by `update()` — `update()` bypasses cache clearing

## Database Schema

**Table:** `settings` (from `packages/marvel/database/migrations/2020_06_02_051901_create_marvel_tables.php`)

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK, auto-increment |
| `site_name` | string | NOT NULL |
| `site_desc` | text | NULLABLE |
| `meta_desc` | text | NULLABLE |
| `site_copy_right` | string | NULLABLE |
| `logo` | string | NULLABLE |
| `favicon` | string | NULLABLE |
| `site_email` | string | NULLABLE |
| `email_support` | string | NULLABLE |
| `facebook` | string | NULLABLE |
| `instagram` | string | NULLABLE |
| `linkedin` | string | NULLABLE |
| `promotion_video_url` | string | NULLABLE |
| `youtube` | string | NULLABLE |
| `phone` | string | NULLABLE |
| `fast_shipping_page_publish` | boolean | DEFAULT true |
| `options` | json | NULLABLE |
| `created_at` | timestamp | NULLABLE |
| `updated_at` | timestamp | NULLABLE |

## Dependencies

| File | Role |
|------|------|
| `packages/marvel/src/Http/Controllers/SettingsController.php` | Controller |
| `packages/marvel/src/Http/Requests/SettingsRequest.php` | Validation |
| `packages/marvel/src/Http/Resources/SettingResource.php` | Response transformation |
| `packages/marvel/src/Database/Models/Settings.php` | Model (with getData) |
| `packages/marvel/src/Database/Repositories/SettingsRepository.php` | Repository |
| `packages/marvel/src/Database/Seeders/SettingsSeeder.php` | Seeder |
| `packages/marvel/src/Events/Maintenance.php` | Event |
| `packages/marvel/src/Listeners/MaintenanceNotification.php` | Listener |
| `packages/marvel/src/Notifications/MaintenanceReminder.php` | Notification |
| `packages/marvel/src/Enums/Permission.php` | Permissions (VIEW_SETTINGS, UPDATE_SETTINGS) |
| `packages/marvel/src/Traits/MediaManager.php` | Media upload trait |
| `app/Http/Controllers/Api/General/SettingController.php` | Public settings controller |
| `app/Services/General/SettingService.php` | Public settings service |
| `packages/marvel/src/Rest/Routes.php` | Route definitions |

## Translation Keys Used

| Key | Context |
|-----|---------|
| `FETCH_DATA_SUCCESSFULLY` | GET response message |
| `SETTINGS_UPDATED_SUCCESSFULLY` | PUT response message |
| `DEFAULT_LANGUAGE` | Default language constant (`config('shop.default_language')`) |
| `NOT_FOUND` | Show/destroy error |
| `ACTION_NOT_VALID` | Destroy not allowed message |
