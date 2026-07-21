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

### Fast Shipping Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 1 | `test_admin_can_fetch_fast_shipping` | Feature | GET /fast-shipping/settings → 200 |
| 2 | `test_admin_can_update_fast_shipping` | Feature | PUT /fast-shipping/settings → 200 |
| 3 | `test_fast_shipping_cache_invalidation` | Feature | Update clears cache |
| 4 | `test_fast_shipping_validation` | Feature | Invalid duration_minutes → 422 |
| 5 | `test_fast_shipping_defaults` | Feature | Empty DB returns default values (enabled: false, 120min, 0 fee) |
