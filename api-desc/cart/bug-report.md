# Bug Report — Cart Module

---

## BUG-CART-001 (BUG-INV-018): Dual Inventory Systems — No Coordination

**Severity:** Critical

**Component:** `app/Services/General/CartInventoryService.php` + `packages/marvel/src/Database/Repositories/OrderRepository.php`

**Description:** Two independent inventory systems operate on the same columns (`stock_quantity`, `reserved_quantity`, `sold_quantity`) with no coordination:

1. **CartInventoryService** — `reserveItem()` → `finalizeCart()` (reserve → release → sold)
2. **OrderRepository::deductStockForOrder()** — directly decrements `stock_quantity`, updates `reserved_quantity`, `sold_quantity`

If a product is added to cart (reserved via CartInventoryService) and then an order is placed via the legacy path (deductStockForOrder), the inventory state becomes inconsistent.

**Code Location:**
- `app/Services/General/CartInventoryService.php` — all reserve/release/finalize methods
- `packages/marvel/src/Database/Repositories/OrderRepository.php` — `deductStock()` method

**Impact:** Critical — can cause overselling or incorrect inventory counts.

**Recommendation:** Standardize on a single inventory flow. Deprecate `deductStockForOrder()` and route all order finalization through `CartInventoryService::finalizeCart()`.

---

## BUG-CART-002 (BUG-INV-004): `finalizeItemsByShippingMethod()` Deletes Non-Finalized Items

**Severity:** High

**Component:** `app/Services/General/CartInventoryService.php`

**Description:** `finalizeItemsByShippingMethod()` is supposed to finalize one shipping group (e.g., SCHEDULED) and keep the other group (FAST) for later processing. Instead, it **releases AND deletes** the other group's items.

**Code Location:** `CartInventoryService::finalizeItemsByShippingMethod()`

**Current Behavior (bug):**
```php
foreach ($itemsToRelease as $releaseItem) {
    $this->releaseStock($stock, $releaseItem->reserved_quantity);
    $releaseItem->delete();  // BUG: items from non-finalized group are deleted
}
```

**Expected Behavior:**
```php
foreach ($itemsToRelease as $releaseItem) {
    $this->releaseStock($stock, $releaseItem->reserved_quantity);
    $releaseItem->update(['reserved_quantity' => 0]);  // Keep item, release reservation
}
```

**Impact:** High — users lose items from the non-finalized shipping group when processing partial checkout.

---

## BUG-CART-003 (BUG-INV-001): Price Snapshotted at Reservation, Not Checkout

**Severity:** High

**Component:** `app/Services/General/CartInventoryService.php`, `packages/marvel/src/Services/Pricing/ProductPricingService.php`

**Description:** Prices are snapshotted when the item is added to the cart (`reserveItem()`). If a flash sale ends or a price changes between add-to-cart and checkout, the user pays the old price. There is no price re-validation at checkout.

**Code Location:** `CartInventoryService::reserveItem()` — price calculation at line ~300

**Impact:** High — users may pay less (if price increased) or the store loses money (if price decreased and item was reserved at higher price). Business decision whether to re-check prices at checkout.

---

## BUG-CART-004 (CONC-7): `total_price` Without Rounding

**Severity:** Low

**Component:** `app/Services/General/CartInventoryService.php`

**Description:** In `reserveItem()`, the line `'total_price' => $price * $desiredQuantity` previously used no rounding. This has been partially fixed — the resource now uses `round(..., 2)` and the repository uses `DB::raw('ROUND(price * quantity, 2)')`.

**Code Location:** `CartInventoryService::reserveItem()` — total_price calculation

**Current State (after fix):**
```php
'total_price' => round($price * $desiredQuantity, 2)
```

**Status:** ✅ Fixed — rounding applied in all locations.

---

## BUG-CART-005: Hardcoded `CART_TTL_DAYS = 3`

**Severity:** Low

**Component:** `app/Services/General/CartInventoryService.php`

**Description:** The cart TTL is hardcoded as `private const CART_TTL_DAYS = 3` instead of being configurable via config/env. Cannot be changed without modifying code.

**Code Location:** `CartInventoryService.php` — line ~25

**Impact:** Low — 3 days is reasonable for most use cases. However, different business requirements (e.g., 24-hour flash sales) require a code change.

---

## BUG-CART-006: Expire Chunk Query Has No Global Lock

**Severity:** Medium

**Component:** `app/Services/General/CartInventoryService.php`

**Description:** `expireCarts()` uses `chunk(100, ...)` without a global lock. If a cart is refreshed between the chunk fetch and the expire operation, the double-check (`if expires_at > now() → skip`) mitigates but doesn't eliminate the race condition.

**Code Location:** `CartInventoryService::expireCarts()`

**Impact:** Medium — a cart that was recently refreshed could be incorrectly expired under heavy load.

---

## BUG-CART-007: `expireCart()` Doesn't Check `status !== 'active'`

**Severity:** Medium

**Component:** `app/Services/General/CartInventoryService.php`

**Description:** The `expireCart()` method releases inventory for any cart passed to it, regardless of its `status`. If a cart's status was already `checked_out` or `expired`, the inventory could be double-released.

**Code Location:** `CartInventoryService::expireCart()` (private)

**Current Behavior:**
```php
// No status check — releases stock regardless
foreach ($cart->items as $item) {
    if ($item->reserved_quantity > 0) {
        $this->releaseStock($stock, $item->reserved_quantity);
    }
}
```

**Recommended Fix:** Add status check before releasing:
```php
if ($cart->status !== 'active') {
    return;  // Skip non-active carts
}
```

---

## BUG-CART-008: Two Duplicate Cart Expire Commands

**Severity:** Low

**Component:** `app/Console/Commands/ExpireCarts.php`, `app/Console/Commands/ExpireAbandonedCarts.php`

**Description:** Both commands call `CartInventoryService::expireCarts()` with identical logic. Having duplicate scheduled commands creates confusion and potential double-execution.

**Code Location:**
- `app/Console/Commands/ExpireCarts.php` — signature: `carts:expire`
- `app/Console/Commands/ExpireAbandonedCarts.php` — signature: `cart:expire`

**Recommendation:** Remove one command and keep a single canonical `carts:expire` command.

---

## BUG-CART-009: No Max Quantity Validation

**Severity:** Low

**Component:** `packages/marvel/src/Http/Requests/CartCreateRequest.php`, `CartUpdateRequest.php`

**Description:** The `quantity` field only validates `min:1` but has no `max` limit. A user could request an excessively large quantity (e.g., `999999999`) which could cause integer overflow or performance issues in the reservation logic.

**Code Location:** Both CartCreateRequest and CartUpdateRequest

**Recommended Fix:** Add `max:9999` (or similar) to the quantity validation rules.
