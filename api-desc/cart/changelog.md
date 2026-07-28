# Cart Module — Changelog

## [1.0.0] — 2026-07-28

### Added
- Cart API with 7 endpoints (list, add, show, update, delete-item, clear, bulk-add)
- CartCreateRequest and CartUpdateRequest Form Requests with validation
- CartResource and CartItemResource for API response transformation
- CartInventoryService with inventory reservation, release, finalize, and expiration
- Row-level locking (`lockForUpdate`) for inventory concurrency
- Rate limiting on cart endpoints (20 req/min per user)
- Cart expiration system with scheduled commands (TTL = 3 days)
- Shipping method separation: SCHEDULED and FAST items in dedicated sections
- Price snapshotting at reservation time via ProductPricingService
- Coupon support: apply, calculate discount, clear on last item delete
- Promotion support: eligibility check, discount application, gift items
- Buld add with soft-deleted product filtering (`skipped_product_ids`)
- Comprehensive documentation (`api-desc/cart/`)
- Comprehensive test suite (70+ tests across 2 test files)

### Fixed
- Cart item `total_price` rounding to 2 decimal places in CartItemResource and CartRepository (SQL `ROUND()`)
- ProductVariantResource `price` and `current_price` rounding to 2 decimal places
- Cart soft-deleted product handling — product returns as null instead of 500 error
- Foreign key cascades changed from `cascadeOnDelete()` to `nullOnDelete()` for user_id and product_id

### Known Issues

1. **BUG-CART-001 (Critical)** — Dual inventory systems: CartInventoryService and OrderRepository::deductStock() operate on same columns with no coordination. Can cause overselling.

2. **BUG-CART-002 (High)** — `finalizeItemsByShippingMethod()` deletes non-finalized shipping group items instead of preserving them.

3. **BUG-CART-003 (High)** — Price snapshotted at reservation, not re-validated at checkout. Stale prices used if flash sale ends between add and checkout.

4. **BUG-CART-005 (Low)** — `CART_TTL_DAYS = 3` hardcoded, not configurable.

5. **BUG-CART-006 (Medium)** — Expire chunk query has no global lock; race condition under heavy load.

6. **BUG-CART-007 (Medium)** — `expireCart()` doesn't check `status !== 'active'` before releasing inventory.

7. **BUG-CART-008 (Low)** — Two duplicate cart expire commands (`carts:expire` and `cart:expire`).

8. **BUG-CART-009 (Low)** — No max quantity validation on add/update requests.
