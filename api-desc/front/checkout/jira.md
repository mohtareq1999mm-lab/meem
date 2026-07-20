# Checkout Module — Backend Jira Tasks

---

## Task 1: Extract Shared Callback Logic into Service

**Priority:** Medium
**Component:** OrderController
**Effort:** Medium
**Files:**
- `app/Http/Controllers/Api/General/OrderController.php`
- New `app/Services/Payment/PaymentCallbackService.php`

**Description:** Both `checkoutCallback()` and `checkoutErrorCallback()` share ~80% duplicated code. Extract common payment verification + transaction update + inventory release logic into a dedicated service.

**Acceptance Criteria:**
- [ ] Callback logic extracted to dedicated service
- [ ] Both callback methods delegate to the service
- [ ] No functional changes

---

## Task 2: Return Different Status for No Cart vs Empty Cart

**Priority:** Low
**Component:** OrderService
**Effort:** Trivial
**Files:**
- `app/Services/General/OrderService.php`
- `app/Http/Controllers/Api/General/OrderController.php`

**Description:** Differentiate "cart not found" (404) from "cart empty" (400) in `eligiblePromotionsForUser()`.

**Acceptance Criteria:**
- [ ] No cart returns 404
- [ ] Empty cart returns 400 with "Cart is empty"

---

## Task 3: Preserve User Locale in Callback Redirects

**Priority:** Low
**Component:** OrderController
**Effort:** Small
**Files:**
- `app/Http/Controllers/Api/General/OrderController.php`

**Description:** Store the user's locale in the transaction or callback URL parameter.

**Acceptance Criteria:**
- [ ] Locale passed as callback query param
- [ ] Redirect URL uses stored locale

---

## Task 4: Lock FAST Items During Checkout

**Priority:** Low
**Component:** OrderService
**Effort:** Small
**Files:**
- `app/Services/General/OrderService.php`

**Description:** `addItemsInOrder()` should include FAST items in the cart lock scope.

**Acceptance Criteria:**
- [ ] Both SCHEDULED and FAST items loaded and locked
- [ ] No regression
