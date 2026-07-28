# Test Coverage — Cart Module

---

## Test Files

| File | Lines | Focus |
|------|-------|-------|
| `tests/Feature/CartApiTest.php` | 1519 | Core API CRUD, inventory, variants, shipping, expiration, edge cases |
| `tests/Feature/CartExpirationTest.php` | 241 | TTL-based cart expiration |

---

## CartApiTest.php Coverage

### Auth Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 1 | `test_cart_index_requires_auth` | Auth | GET /cart without token → 401 |
| 2 | `test_add_item_requires_auth` | Auth | POST /cart without token → 401 |
| 3 | `test_update_item_requires_auth` | Auth | PUT /update-item without token → 401 |
| 4 | `test_delete_item_requires_auth` | Auth | DELETE /delete-item without token → 401 |
| 5 | `test_clear_cart_requires_auth` | Auth | DELETE /delete-items without token → 401 |
| 6 | `test_cart_show_requires_auth` | Auth | GET /cart/{id} without token → 401 |
| 7 | `test_guest_cannot_access_cart` | Auth | All endpoints without token → 401 |

### Index Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 8 | `test_cart_index_returns_empty_when_no_cart` | Feature | No cart yet → 200 with empty data |

### Add Item Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 9 | `test_add_item_creates_cart` | Feature | First item creates a new cart |
| 10 | `test_add_item_reserves_inventory` | Feature | Stock reserved_quantity incremented |
| 11 | `test_add_item_rejects_excessive_quantity` | Validation | Quantity > available stock → 400 |
| 12 | `test_add_item_rejects_nonexistent_product` | Validation | Invalid product_id → 422 |
| 13 | `test_add_multiple_items_accumulates_in_cart` | Feature | Multiple adds create separate items |

### Update Item Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 14 | `test_update_item_changes_quantity` | Feature | Set mode → quantity updated |
| 15 | `test_update_item_adjusts_reserved_quantity` | Feature | Reserved quantity matches new quantity |
| 16 | `test_update_item_rejects_excessive_quantity` | Validation | Set quantity > stock → 400 |
| 17 | `update_item_to_zero_rejected` | Validation | Quantity = 0 → 422 |

### Delete Item Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 18 | `test_delete_item_releases_inventory` | Feature | Stock released after deletion |
| 19 | `test_delete_item_removes_item_from_cart` | Feature | Item removed from DB |
| 20 | `test_delete_item_returns_400_for_nonexistent_item` | Edge Case | Invalid itemId → 400 |

### Clear Cart Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 21 | `test_clear_cart_releases_all_inventory` | Feature | All stock released |
| 22 | `test_destroy_returns_404_when_no_cart` | Edge Case | No cart → 404 |
| 23 | `clear_cart_without_confirm_and_no_coupon_succeeds` | Feature | No coupon → clears directly |
| 24 | `clear_cart_with_coupon_without_confirm_returns_warning` | Feature | Has coupon + no confirm → 400 warning |

### Show Cart Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 25 | `test_cart_show_returns_cart` | Feature | GET /cart/{id} → cart data |
| 26 | `test_cart_show_rejects_other_user_cart` | Auth | Other user's cart → 403 |
| 27 | `cart_show_rejects_nonexistent_cart` | Edge Case | Invalid ID → 404 |

### Bulk Add Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 28 | `test_bulk_add_items` | Feature | Multiple items added successfully |
| 29 | `test_bulk_add_validates_items` | Validation | Invalid items array → 422 |
| 30 | `test_bulk_add_rolls_back_on_failure` | Transaction | One invalid item → all rejected |
| 31 | `bulk_add_mixed_shipping_methods` | Feature | Mixed SCHEDULED + FAST → correct sections |

### Shipping Method Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 32 | `test_shipping_method_lowercase_is_normalized_to_uppercase` | Validation | `scheduled` → stored as `SCHEDULED` |
| 33 | `test_shipping_method_uppercase_is_stored_as_is` | Validation | `SCHEDULED` → stored as `SCHEDULED` |
| 34 | `test_cart_sections_return_items_in_correct_section` | Feature | Items in correct normal_items / fast_items |

### Same Product Diff Shipping Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 35 | `same_product_different_shipping_creates_separate_items` | Feature | Two items for same product, diff shipping |
| 36 | `update_cart_item_preserves_shipping_method` | Feature | Update without shipping → method preserved |
| 37 | `update_with_explicit_shipping_method_updates_correct_item` | Feature | Update with shipping → correct item updated |

### Variant Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 38 | `add_variant_product_to_cart` | Feature | Variable product with variant_id |
| 39 | `update_variant_item_preserves_shipping_method` | Feature | Update variant item → method preserved |
| 40 | `delete_variant_item_releases_variant_stock` | Feature | Delete variant → variant stock released |
| 41 | `same_product_different_variants_create_separate_items` | Feature | Two items, same product, diff variants |

### Stock Consistency Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 42 | `stock_consistency_after_multiple_add_remove_cycles` | Regression | Stock correct after add/remove/add |
| 43 | `stock_consistency_after_quantity_update` | Regression | Stock correct after quantity update |

### Total Accuracy Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 44 | `cart_total_price_updated_on_item_operations` | Feature | Total recalculated after add/update/delete |

### Coupon Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 45 | `test_apply_coupon_to_cart` | Feature | Coupon applied to cart |
| 46 | `delete_last_item_clears_coupon` | Feature | Last item deleted → coupon cleared |

### Expiration Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 47 | `expired_cart_status_correct_after_expiry` | Feature | Status = 'expired' after TTL |
| 48 | `add_item_reactivates_expired_cart_and_re_reserves_stock` | Feature | Expired → new add creates fresh cart |
| 49 | `recently_refreshed_cart_not_expired` | Feature | Cart within TTL not expired |

### Edge Case Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 50 | `test_cart_response_handles_soft_deleted_product` | Edge Case | Product soft-deleted → product null in response |
| 51 | `cart_response_handles_deleted_coupon` | Edge Case | Coupon deleted → coupon null in response |
| 52 | `gift_item_attribute_not_exposed_in_item_resource` | Feature | Gift items don't expose extra attributes |

### Finalization Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 53 | `finalize_all_items_marks_cart_checked_out` | Feature | All items finalized → status = checked_out |
| 54 | `finalize_scheduled_items_only_keeps_fast_items` | Feature | Only FAST items remaining after finalize SCHEDULED |
| 55 | `finalize_fast_items_only_keeps_scheduled_items` | Feature | Only SCHEDULED items remaining after finalize FAST |

### Reservation Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 56 | `ensure_cart_reservation_syncs_quantities` | Feature | Reservation synced to current quantities |
| 57 | `release_cart_without_delete_releases_stock_but_keeps_items` | Feature | Stock released, items kept |

### Response Structure Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 58 | `cart_response_structure_is_correct` | Feature | JSON structure matches expected |
| 59 | `test_english_cart_messages_are_readable` | Translation | Response messages in English |

### Rate Limiting Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 60 | `cart_rate_limiter_enforces_limit` | Security | >20 req/min → 429 |

### Additional Tests

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 61 | `update_non_existent_cart_item_creates_new_item` | Edge Case | Update on non-existent product_id → creates new item |
| 62 | `multiple_adds_accumulate_quantity` | Feature | Same product+variant+shipping added twice → quantities summed |

---

## CartExpirationTest.php Coverage

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 1 | `expire_carts_releases_reserved_stock` | Feature | Stock released after TTL |
| 2 | `expire_carts_marks_cart_as_expired` | Feature | Status changed to 'expired' |
| 3 | `expire_carts_deletes_cart_items` | Feature | Items deleted after expiry |
| 4 | `active_cart_not_expired_before_ttl` | Feature | Cart within TTL not expired |
| 5 | `expire_carts_skips_carts_without_expires_at` | Edge Case | null expires_at → skipped |
| 6 | `cart_receives_3day_ttl_on_touch` | Feature | 3-day TTL set on reserve |
| 7 | `expired_carts_can_be_recreated` | Feature | New cart after expiry |
| 8 | `cart_expired_before_finalize_releases_stock` | Edge Case | Stock freed after expiry before checkout |

---

## Coverage Summary

| Category | Count |
|----------|-------|
| Auth Tests | 7 |
| Feature Tests (Success) | ~25 |
| Validation Tests | ~6 |
| Edge Case Tests | ~6 |
| Regression Tests | ~2 |
| Security Tests | ~1 |
| Translation Tests | ~1 |
| Transaction Tests | ~1 |
| **Total (estimate)** | ~70 |

---

## Missing Tests (Recommended)

- [ ] **FAST shipping eligibility** — add item with FAST to product without `is_fast_shipping_available` → 400 error
- [ ] **Bulk add with soft-deleted product** — soft-deleted product should appear in `skipped_product_ids`
- [ ] **Cart with expired cart (both status + TTL)** — expired status but still within TTL → should still be expired
- [ ] **Multiple carts for same user** — verify one active cart per user constraint
- [ ] **Coupon application** — test coupon validation errors (minimum cart amount, expired coupon)
- [ ] **Concurrent add to cart (race condition)** — two simultaneous requests for same product → no oversell
- [ ] **Concurrent finalize (race condition)** — two simultaneous checkouts for same item → one fails
- [ ] **Promotion application** — verify discount_amount and gift items are created
- [ ] **Promotion cleared on item add** — after adding item with active promotion, verify promotion cleared
- [ ] **Cart total_price precision** — verify no floating point drift after many operations
- [ ] **Reserved inventory released on checkout failure** — if checkout fails, inventory should be released
- [ ] **Large quantity test** — add MAX_INT quantity → overflow/truncation behavior
- [ ] **Empty cart bulk add** — `items: []` → 422 or 200?
- [ ] **Mix of valid + invalid in bulk add** — verify skipped_product_ids includes all invalid ones
- [ ] **Delete item from empty cart** → 404
- [ ] **Update item with different shipping method** → should create separate item or update the right one
