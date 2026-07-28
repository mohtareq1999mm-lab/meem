# Cart Module — QA Test Cases

## Test Files

- `tests/Feature/CartApiTest.php` — 1519 lines
- `tests/Feature/CartExpirationTest.php` — 241 lines

---

## API Functionality Tests

| # | Test | Description | Expected |
|---|------|-------------|----------|
| F1 | List carts (empty) | GET /cart with no cart | 200, empty data array |
| F2 | List carts (with items) | GET /cart with existing cart | 200, cart with items |
| F3 | Add item (simple product) | POST /cart with valid product | 201, item created |
| F4 | Add item (variant product) | POST /cart with variant_id | 201, variant item created |
| F5 | Add item (FAST shipping) | POST /cart with FAST method | 201, item in fast_items |
| F6 | Add same product twice | POST /cart twice same product+variant | 201, quantity accumulated |
| F7 | Show cart by ID | GET /cart/1 | 200, cart data |
| F8 | Update item quantity (set) | PUT /update-item with new quantity | 200, quantity updated |
| F9 | Delete single item | DELETE /delete-item/1 | 200, item removed |
| F10 | Clear entire cart | DELETE /delete-items | 200, cart empty |
| F11 | Bulk add items | POST /bulk-items with 2+ items | 200, all items added |

---

## Validation Tests

| # | Test | Description | Expected |
|---|------|-------------|----------|
| V1 | Add item without product_id | Missing required field | 422 |
| V2 | Add item with zero quantity | quantity = 0 | 422 |
| V3 | Add item with negative quantity | quantity = -1 | 422 |
| V4 | Add item with invalid product_id | Non-existent product | 422 |
| V5 | Add item with invalid shipping_method | Wrong string | 422 |
| V6 | Add item with non-existent variant_id | Invalid variant | 422 |
| V7 | Update with zero quantity | quantity = 0 | 422 |
| V8 | Bulk add with empty items array | items = [] | 422 |
| V9 | Bulk add with missing product_id in one item | Partial invalid data | 422 |

---

## Authorization Tests

| # | Test | Description | Expected |
|---|------|-------------|----------|
| A1 | Guest cannot list carts | No auth token | 401 |
| A2 | Guest cannot add item | No auth token | 401 |
| A3 | Guest cannot update item | No auth token | 401 |
| A4 | Guest cannot delete item | No auth token | 401 |
| A5 | Guest cannot clear cart | No auth token | 401 |
| A6 | User cannot view another user's cart | Wrong user_id | 403 |

---

## Business Logic Tests

| # | Test | Description | Expected |
|---|------|-------------|----------|
| B1 | Add item reserves inventory | Check reserved_quantity incremented | Stock reserved |
| B2 | Delete item releases inventory | Check reserved_quantity decremented | Stock released |
| B3 | Update quantity (increase) reserves more | Old qty=2, new qty=5 → +3 reserved | Stock delta correct |
| B4 | Update quantity (decrease) releases excess | Old qty=5, new qty=2 → -3 released | Stock delta correct |
| B5 | Add item with quantity > stock | Qty exceeds available_stock | 400 error |
| B6 | Update to quantity > stock | New qty exceeds available_stock | 400 error |
| B7 | Clear cart releases all inventory | All stock released | Stock correct |
| B8 | Bulk add with mixed stock levels | Some items over stock | Subset added or failed |
| B9 | Cart total_price recalculated after operations | Add/update/delete → total updates | Total matches sum |
| B10 | Same product, different shipping = separate items | Two items with SCHEDULED + FAST | Separate items in sections |
| B11 | Update preserves shipping method when omitted | No shipping_method in PUT | Existing method kept |
| B12 | Delete last item clears coupon | Last item deleted + had coupon | Coupon null |

---

## Shipping Method Tests

| # | Test | Description | Expected |
|---|------|-------------|----------|
| S1 | Lowercase shipping_method normalized | `scheduled` → `SCHEDULED` | Stored uppercase |
| S2 | FAST on non-eligible product | Product without fast shipping | 400 error |
| S3 | Items in correct section | SCHEDULED in normal_items, FAST in fast_items | Correct split |

---

## Variant Tests

| # | Test | Description | Expected |
|---|------|-------------|----------|
| VR1 | Add variable product without variant_id | Variable product without variant | 400 error |
| VR2 | Add variable product with variant_id | Valid variant_id | 201, item created |
| VR3 | Same product, different variants | Two variants of same product | Separate items |
| VR4 | Update variant item | Quantity change on variant | Works correctly |
| VR5 | Delete variant item | Remove variant item | Variant stock released |

---

## Coupon Tests

| # | Test | Description | Expected |
|---|------|-------------|----------|
| C1 | Apply coupon to cart | Valid coupon code | Coupon stored |
| C2 | Clear cart with coupon without confirm | Coupon applied, no confirm | 400 warning |
| C3 | Clear cart with coupon with confirm | Coupon applied, confirm=true | 200, cart cleared |
| C4 | Delete last item clears coupon | Last item removed | Coupon null |

---

## Expiration Tests

| # | Test | Description | Expected |
|---|------|-------------|----------|
| E1 | Cart expires after TTL | expires_at passed | status = expired, items deleted |
| E2 | Cart within TTL not expired | expires_at in future | status = active |
| E3 | Stock released on expiry | Expired cart → reserved stock freed | Stock correct |
| E4 | Add item to expired cart creates new | Old cart expired, new add | Fresh cart created |

---

## Rate Limiting Tests

| # | Test | Description | Expected |
|---|------|-------------|----------|
| R1 | 21 requests in one minute | Exceeds 20/min limit | 429 Too Many Requests |
| R2 | 20 requests in one minute | Within limit | All 200 |

---

## Edge Case Tests

| # | Test | Description | Expected |
|---|------|-------------|----------|
| EC1 | Show cart with non-existent ID | GET /cart/99999 | 404 |
| EC2 | Show another user's cart | ID of cart owned by different user | 403 |
| EC3 | Delete non-existent item | DELETE /delete-item/99999 | 400 |
| EC4 | Clear cart when no cart exists | DELETE /delete-items without cart | 404 |
| EC5 | Product soft-deleted after add | Product deleted → response shows null product | Product field null |
| EC6 | Coupon deleted after applied | Coupon removed → response shows null | Coupon null |
| EC7 | Response handles gift items | Gift item has price=0, is_gift=true | Correct structure |
| EC8 | Stock consistency after add/remove cycles | Multiple add/remove cycles | Stock returns to original |
| EC9 | Stock consistency after quantity updates | Multiple increase/decrease cycles | Stock matches expected |
| EC10 | Cart with no items | Empty cart (e.g., all items deleted) | total_items = 0, total_price = 0 |

---

## Missing Coverage

- [ ] **Concurrent add to cart** — two simultaneous requests for same product (race condition)
- [ ] **Concurrent finalize** — two simultaneous checkouts for same item
- [ ] **FAST shipping eligibility error** — complete test with proper mock
- [ ] **Promotion application flow** — discount_amount and gift items created correctly
- [ ] **Promotion clearing** — promotion cleared on item add
- [ ] **Update item with different shipping method** — changing method creates separate item
- [ ] **Bulk add with mix of valid and invalid products** — skipped_product_ids accuracy
- [ ] **Very large quantity** — boundary/overflow testing
- [ ] **Finalize by shipping method** — finalize SCHEDULED, verify FAST items remain (known bug)
- [ ] **Expired cart finalize fails** — finalize an already-expired cart
