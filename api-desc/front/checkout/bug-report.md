# Bug Report — Checkout Module (Public + Authenticated API)

---

## BUG-CHK-001: `eligiblePromotions` Returns 400 for Both "No Cart" and "Cart Empty"

**Severity:** Low

**Component:** `app/Services/General/OrderService.php` (lines 233-241)

**Description:** `eligiblePromotionsForUser()` returns null both when the cart doesn't exist and when the cart has no items. The controller maps both to `CART_NOT_FOUND` with 400.

---

## BUG-CHK-002: `checkoutCallback` and `checkoutErrorCallback` Share Large Duplicated Logic

**Severity:** Medium

**Component:** `OrderController.php` (lines 169-398)

**Description:** Both callback methods contain nearly identical logic (~230 lines duplicated) for verifying payment, updating transactions, releasing cart, and redirecting.

---

## BUG-CHK-003: Checkout Does Not Lock FAST Items Atomically

**Severity:** Low

**Component:** `OrderService.php` (lines 146-231)

**Description:** `addItemsInOrder()` locks the cart but only loads SCHEDULED items. FAST items are not in scope — a concurrent fast-shipping checkout could interfere.

---

## BUG-CHK-004: `getTransactionQr` Returns 404 for Missing UUID but 403 for Unauthorized

**Severity:** Low

**Component:** `OrderController.php` (lines 150-166)

**Description:** Null transaction → 404. Valid transaction with deleted order → 403. But deleted order on valid transaction → 404.

---

## BUG-CHK-005: Order Status Transitions Hardcoded

**Severity:** Low

**Component:** `OrderService.php` (lines 446-452)

**Description:** Allowed transitions are in a private static array. New statuses require code changes. No revert possible from completed → processing.

---

## BUG-CHK-006: Callback Redirects Always Use `app_locale`

**Severity:** Low

**Component:** `OrderController.php` (lines 230, 277, 319, 393)

**Description:** Callback redirects use `app()->getLocale()` but the payment gateway callback may not preserve the user's session locale.

---

## BUG-CHK-007: `refreshCartItemPrices` Only Sees SCHEDULED Items

**Severity:** Low

**Component:** `OrderService.php` (lines 369-398)

**Description:** `refreshCartItemPrices()` iterates `$cart->items` but the cart only has SCHEDULED items loaded. FAST items' prices are stale.
