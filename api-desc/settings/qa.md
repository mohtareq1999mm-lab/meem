# Settings Module — QA Test Cases

## Total Test Cases: 22 (all passing)

### Regression Tests (10 tests, 19 assertions)

| # | Test | Description | Expected |
|---|------|-------------|----------|
| R1 | `getData_returns_settings_when_exists` | `Settings::getData()` returns model when record exists | Instance of Settings, options match |
| R2 | `getData_returns_null_when_no_settings` | `Settings::getData()` returns null when table empty | Null |
| R3 | `getData_with_language_returns_same_result` | Language parameter doesn't filter (no language column) | Same result |
| R4 | `getData_caches_the_result` | Cache is populated after first call | Cache::has('cached_settings_en') |
| R5 | `getData_uses_different_cache_key_per_language` | Different languages use different cache keys | Both keys exist |
| R6 | `getData_cache_is_stored_with_key_per_language` | Cache stored with correct key | Key exists |
| R7 | `getData_returns_cached_result_without_database_query` | Cached result survives table truncation | Old data returned from cache |
| R8 | `getData_does_not_cache_null_result` | Null is not cached; subsequent data is fresh | Returns new data |
| R9 | `settings_can_be_read_after_update` | `setTranslation()` then `fresh()` reads correctly | Updated value |
| R10 | `options_are_cast_to_array` | `options` attribute is an array | Is array, matches input |

### CRUD Tests (3 tests, 6 assertions)

| # | Test | Description | Expected |
|---|------|-------------|----------|
| C1 | `can_view_settings` | GET returns 200 with success | 200, success=true |
| C2 | `can_update_settings` | PUT with valid data, logo, favicon returns 200 | 200, success=true, name updated |
| C3 | `settings_returns_expected_json_structure` | Response has all 16 data fields | Correct structure |

### Authentication Tests (3 tests, 3 assertions)

| # | Test | Description | Expected |
|---|------|-------------|----------|
| A1 | `guests_can_view_settings` | Unauthenticated GET returns 200 | 200 (public) |
| A2 | `guests_cannot_update_settings` | Unauthenticated PUT returns 401 | 401 |
| A3 | `user_without_permission_cannot_update_settings` | Authenticated user without `update-settings` gets 403 | 403 |

### Validation Tests (6 tests, 6 assertions)

| # | Test | Description | Expected |
|---|------|-------------|----------|
| V1 | `update_returns_422_without_site_name` | Missing site_name | 422 |
| V2 | `update_returns_422_without_site_email` | Missing site_email | 422 |
| V3 | `update_returns_422_with_invalid_email` | Invalid site_email format | 422 |
| V4 | `update_returns_422_with_invalid_url` | Invalid facebook URL | 422 |
| V5 | `update_returns_422_without_fast_shipping_page_publish` | Missing fast_shipping_page_publish | 422 |
| V6 | `update_returns_422_with_invalid_fast_shipping_value` | Invalid value for fast_shipping_page_publish | 422 |

## Edge Cases to Cover

## Missing Coverage

- **Cache invalidation on `update()`** — Not tested (cache not cleared on PUT)
- **Media upload failure** — What happens when `updateSingleImage` fails
- **Maintenance event dispatch** — Not tested
- **GraphQL mutation path** — `SettingsMutator` → `store()` not tested
- **Concurrent updates** — No transaction conflict test
