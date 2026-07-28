# Cart Module — Jira Tasks

---

## Task 1: Extract Inline Validation Into CartCreateRequest / CartUpdateRequest

**Priority:** High
**Component:** Cart Controller
**Effort:** Small
**Files:**
- `packages/marvel/src/Http/Requests/CartCreateRequest.php`
- `packages/marvel/src/Http/Requests/CartUpdateRequest.php`

**Description:** CartCreateRequest and CartUpdateRequest are already extracted — validation is properly in Form Requests. No action needed.

**Status:** ✅ Already implemented

---

## Task 2: Add `whereNumber('id')` Validation on Show Route

**Priority:** Low
**Component:** Routes
**Effort:** Trivial
**Files:**
- `packages/marvel/src/Rest/Routes.php`

**Description:** The `GET /cart/{id}` route has `->whereNumber('id')` to ensure only numeric IDs are accepted. This prevents slug-based lookups and ensures consistent behavior.

**Status:** ✅ Already implemented

---

## Task 3: Separate Cart Routes Into Dedicated Route File

**Priority:** Low
**Component:** Routes
**Effort:** Small
**Files:**
- `packages/marvel/src/Rest/Routes.php`

**Description:** All cart routes are defined inline within the API group middleware in Routes.php. Consider extracting to a dedicated `cart.php` route file for better organization.

**Acceptance Criteria:**
- [ ] Cart routes moved to `routes/cart.php`
- [ ] Included from Routes.php or RouteServiceProvider
- [ ] All existing tests pass

---

## Task 4: Handle Cart Expiration Race Condition in Chunk Query

**Priority:** Medium
**Component:** Cart Inventory Service
**Effort:** Small
**Files:**
- `app/Services/General/CartInventoryService.php`

**Description:** The `expireCarts()` method uses a chunk query without a global lock. If a cart is updated between the chunk fetch and the expire operation, the cart's `expires_at` might have been refreshed. The code does a double-check, but there's no lock on the chunk query itself. Use `lockForUpdate` on the chunk query or process carts one-by-one with locking.

**Acceptance Criteria:**
- [ ] Chunk query uses `lockForUpdate()` or processes carts individually with locking
- [ ] No race condition where a refreshed cart gets expired

---

## Task 5: Replace Hardcoded CART_TTL_DAYS With Config

**Priority:** Low
**Component:** Cart Inventory Service
**Effort:** Trivial
**Files:**
- `app/Services/General/CartInventoryService.php`
- `config/cart.php` (new)

**Description:** `CART_TTL_DAYS = 3` is hardcoded as a class constant. Extract to a config file (`config/cart.php`) with an env variable override.

**Acceptance Criteria:**
- [ ] `config/cart.php` created with `'ttl_days' => env('CART_TTL_DAYS', 3)`
- [ ] `CartInventoryService` reads from config instead of constant
- [ ] All existing tests pass

---

## Task 6: Fix `finalizeItemsByShippingMethod()` — Don't Release Other Shipping Group

**Priority:** High
**Component:** Cart Inventory Service
**Effort:** Medium
**Files:**
- `app/Services/General/CartInventoryService.php`

**Description:** `finalizeItemsByShippingMethod()` currently finalizes one shipping group and **releases AND deletes** all items in the other shipping group. Instead, it should only release the reserved stock for those items but keep them in the cart (not delete them), so the user can still process the other group later.

**Current Behavior:**
```php
foreach ($itemsToRelease as $releaseItem) {
    $this->releaseStock($stock, $releaseItem->reserved_quantity);  // Release stock
    $releaseItem->delete();  // DELETE item — BUG
}
```

**Expected Behavior:**
```php
foreach ($itemsToRelease as $releaseItem) {
    $this->releaseStock($stock, $releaseItem->reserved_quantity);  // Release stock only
    $releaseItem->update(['reserved_quantity' => 0]);  // Keep item, release reservation
}
```

**Acceptance Criteria:**
- [ ] Items in the non-finalized shipping group are preserved in the cart
- [ ] Only reserved stock is released for those items
- [ ] Finalized items are deleted
- [ ] Cart status set to `checked_out` only when all shipping groups are finalized

---

## Task 7: Add Comprehensive Cart Test Suite

**Priority:** High
**Component:** Tests
**Effort:** Medium
**Files:**
- `tests/Feature/CartApiTest.php`

**Description:** Current test suite is comprehensive (70+ tests) but has gaps:
- Test FAST shipping eligibility validation (product without fast shipping)
- Test concurrent add to cart (race condition)
- Test concurrent finalize (race condition)
- Test promotion application and clearing
- Test finalizeItemsByShippingMethod bug

**Acceptance Criteria:**
- [ ] FAST shipping eligibility test added
- [ ] Race condition tests added (concurrent operations)
- [ ] Promotion lifecycle tests added
- [ ] Finalize by shipping method tests added

---

## Task 8: Merge Duplicate Expire Commands

**Priority:** Low
**Component:** Console
**Effort:** Trivial
**Files:**
- `app/Console/Commands/ExpireCarts.php`
- `app/Console/Commands/ExpireAbandonedCarts.php`

**Description:** Two commands (`carts:expire` and `cart:expire`) do the exact same thing — call `CartInventoryService::expireCarts()`. Remove the duplicate and keep one command with the correct signature.

**Acceptance Criteria:**
- [ ] One command removed
- [ ] Kernel schedule updated to use the remaining command
- [ ] All expiration tests pass

---

## Task 9: Add Guard Against Quantity Overflow

**Priority:** Low
**Component:** Cart Inventory Service
**Effort:** Small
**Files:**
- `app/Services/General/CartInventoryService.php`

**Description:** No upper bound check on `quantity` or `desiredQuantity` beyond `>= 1`. A very large quantity could cause integer overflow or performance issues. Add a reasonable maximum quantity validation (e.g., `max:9999`).

**Acceptance Criteria:**
- [ ] CartCreateRequest / CartUpdateRequest add `max:9999` rule to `quantity`
- [ ] `reserveItem()` also validates desiredQuantity against a max
- [ ] 422 returned for excessive quantities
