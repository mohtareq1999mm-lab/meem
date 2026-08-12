# Test Coverage — Settings Module (Admin API)

---

## Existing Tests

None for admin settings endpoints.

---

## Recommended Tests

### Settings CRUD Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 1 | `test_admin_can_fetch_settings` | Feature | GET /settings returns 200 |
| 2 | `test_admin_can_update_settings` | Feature | PUT /settings with valid data |
| 3 | `test_update_settings_unauthorized` | Feature | PUT without token → 401 |
| 4 | `test_update_settings_forbidden` | Feature | PUT without permission → 403 |
| 5 | `test_update_settings_validation` | Feature | PUT invalid data → 422 |
| 6 | `test_update_minimum_order_amount` | Feature | Set minimumOrderAmount, verify in GET |
| 7 | `test_update_currency_selection_enabled` | Feature | PUT `currency_selection_enabled: true` → 200; reflected in GET response |
| 8 | `test_currency_selection_enabled_merges_options` | Feature | Setting the flag preserves `fast_shipping`/other option keys |
| 9 | `test_currency_selection_enabled_invalid` | Validation | PUT `currency_selection_enabled: "yes"` → 422 |
| 10 | `test_settings_response_includes_currency_selection_enabled` | Structure | GET (admin + public) exposes top-level bool |
| 11 | `test_guest_cannot_access_admin_settings` | Auth | GET /api/v1/settings without token → 401 |
| 12 | `test_public_settings_endpoint` | Feature | GET /api/v1/general/settings without token → 200 (includes `currency_selection_enabled`)

### Fast Shipping Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 1 | `test_admin_can_fetch_fast_shipping` | Feature | GET /fast-shipping/settings → 200 |
| 2 | `test_admin_can_update_fast_shipping` | Feature | PUT /fast-shipping/settings → 200 |
| 3 | `test_fast_shipping_cache_invalidation` | Feature | Update clears cache |
| 4 | `test_fast_shipping_validation` | Feature | Invalid duration_minutes → 422 |
| 5 | `test_fast_shipping_defaults` | Feature | Empty DB returns default values (enabled: false, 120min, 0 fee) |
