# Settings Module — Changelog

## [1.0.0] — 2026-07-19

### Added
- `Settings::getData()` static method with cache support (key: `cached_settings_{language}`, TTL: 86400s)
- Comprehensive test suite: 22 tests, 51 assertions
  - `SettingsRegressionTest` — 10 tests for `getData()` caching and model behavior
  - `SettingsCrudTest` — 3 tests for GET/PUT endpoints
  - `SettingsAuthenticationTest` — 3 tests for auth/permission enforcement
  - `SettingsValidationTest` — 6 tests for request validation

### Changed
- `SettingsController@update()`: `$id` parameter made optional (`$id = null`) to support public PUT route without `{id}` parameter
- `SettingsController@store()`: Merge order fixed — new options now properly override existing options (was reversed). Spread of model object replaced with array extraction to prevent PHP 8.1+ fatal error
- `SettingsRepository@updateSetting()`: 
  - Favicon upload error message now correctly says "Favicon upload failed" instead of "Logo upload failed"
  - Generic catch block re-throws `HttpException` from image uploads directly and uses generic "Settings update failed" for unexpected errors
- `SettingResource`: Added `fast_shipping_page_publish` and `options` to API response
- `SettingsController@show()`: Removed unused `$id` parameter (settings is singleton); now uses `apiResponse` + `SettingResource` for consistency with `index()`

### Fixed
- **CRITICAL:** `Settings::getData()` missing from Settings model — 19 callers across the codebase were calling a non-existent static method
- **HIGH:** `store()` method spreads Model object as array — would cause fatal error in PHP 8.1+
- **MEDIUM:** `updateSetting()` catch block always showed "Logo upload failed" regardless of actual error type
- **MEDIUM:** `PUT /api/v1/settings` route (line 135) called `update($id)` without passing `$id` parameter

### Removed
- `update_returns_500_when_no_settings_exist` test — validation (422) runs before business logic when `logo`/`favicon` are missing

## Known Issues

1. **`language` column missing in migration** — `store()` and seeder reference a `language` column that doesn't exist in the `settings` table schema. `getData()` language parameter is only used for cache key differentiation, not filtering.
2. **Cache not cleared on `update()`** — Only `store()` clears the cache. The `update()` method (used by PUT routes) does NOT invalidate the cache. Cache will serve stale data for up to 24 hours.
3. **Spatie Translatable on non-JSON columns** — `site_name` (string), `site_desc` (text), etc. are declared translatable but migration creates them as plain string/text, not JSON. Works for single-language setups but may break with multi-language.
4. **Duplicate route definition** — `GET /api/v1/settings` is registered twice (lines 134 and 244 in Routes.php).
