# Cart Module — Frontend Jira Tasks

## Task 1: Shopping Cart Page — Full Cart View

**Priority:** High
**Component:** Frontend — Cart Page
**Story Points:** 8

**Description:** Build the main shopping cart page displaying all items grouped by shipping method, with totals, coupon input, and checkout CTA.

**API Endpoints:**
- `GET /api/v1/cart`
- `PUT /api/v1/cart/update-item`
- `DELETE /api/v1/cart/delete-item/{itemId}`
- `DELETE /api/v1/cart/delete-items`

**Acceptance Criteria:**
- [ ] Cart loads on mount with loading skeleton
- [ ] Items displayed in two sections: "Standard Delivery" (SCHEDULED) and "Express Delivery" (FAST)
- [ ] Each item shows: product thumbnail, name, price, quantity selector, delete button
- [ ] Quantity selector: +/- buttons with debounced PUT on change
- [ ] Delete button: confirm dialog then DELETE request
- [ ] Cart summary section: subtotal, coupon discount, total after coupon
- [ ] Coupon code input field with "Apply" button
- [ ] "Clear Cart" button with confirmation dialog (if coupon applied, requires additional confirm)
- [ ] "Proceed to Checkout" button
- [ ] Cart expiration countdown timer (from `expires_at`)
- [ ] **Loading state:** Skeleton placeholders for cart items (3-4 rows)
- [ ] **Empty state:** "Your cart is empty" illustration with "Start Shopping" button
- [ ] **Error state:** Toast for API errors with retry option

---

## Task 2: Add to Cart Button — Product Pages

**Priority:** High
**Component:** Frontend — Product Detail / Listing
**Story Points:** 5

**Description:** Implement "Add to Cart" button on product detail and listing pages with quantity selection and shipping method choice.

**API Endpoint:**
- `POST /api/v1/cart`

**Acceptance Criteria:**
- [ ] "Add to Cart" button on product detail page
- [ ] Quantity selector (min: 1, with +/- buttons)
- [ ] Shipping method selector: Standard / Express (if product supports FAST)
- [ ] For variable products: variant selector before add to cart
- [ ] On click: POST to cart, show success toast
- [ ] Update cart badge count in header
- [ ] Disable button during API call
- [ ] **Loading state:** Button shows spinner during add
- [ ] **Error state (stock):** Toast "Quantity exceeds available stock"
- [ ] **Error state (auth):** Redirect to login if not authenticated
- [ ] **Error state (rate limit):** Toast "Please wait before adding more items"

---

## Task 3: Cart Badge / Mini Cart — Header Component

**Priority:** Medium
**Component:** Frontend — Header / Navigation
**Story Points:** 3

**Description:** Display a cart icon with item count in the header, with optional mini-cart dropdown on hover/click.

**API Endpoint:**
- `GET /api/v1/cart` (poll or cache)

**Acceptance Criteria:**
- [ ] Cart icon shows total quantity badge
- [ ] Badge updates after add/update/delete operations
- [ ] Click navigates to full cart page
- [ ] Optional: hover dropdown shows mini-cart with first 3 items + total
- [ ] **Empty state:** Badge hidden or shows 0
- [ ] **Loading state:** Badge shows current cached value
- [ ] **Error state:** Badge keeps last known value

---

## Task 4: Cart — Quantity Update with Debounce

**Priority:** Medium
**Component:** Frontend — Cart Item Controls
**Story Points:** 3

**Description:** Implement quantity update with debounce to avoid excessive API calls when user types/uses +/- buttons.

**API Endpoint:**
- `PUT /api/v1/cart/update-item`

**Acceptance Criteria:**
- [ ] +/- buttons immediately update UI (optimistic)
- [ ] Auto-debounce (300ms) before sending PUT request
- [ ] Disable +/- buttons during API call
- [ ] On stock error: revert to previous quantity with error toast
- [ ] **Loading state:** Quantity input shows loading indicator
- [ ] **Error state (stock exceeded):** Revert quantity, show "Only X in stock"
- [ ] **Error state (network):** Revert to previous value with toast

---

## Task 5: Coupon Input — Cart Page

**Priority:** Medium
**Component:** Frontend — Coupon
**Story Points:** 3

**Description:** Coupon code input field with apply/remove functionality.

**API Endpoint:**
- `POST /api/v1/coupons/add-to-cart`

**Acceptance Criteria:**
- [ ] Text input for coupon code
- [ ] "Apply" button sends code to API
- [ ] On success: show discount breakdown, update total
- [ ] On error (invalid/expired/minimum not met): inline error message
- [ ] Applied coupon shown as a tag with "Remove" button
- [ ] Remove sends DELETE request or triggers coupon clear
- [ ] **Loading state:** Button shows spinner during apply
- [ ] **Empty state:** Clean input with placeholder text
- [ ] **Error state:** Red border + error message below input

---

## Task 6: Cart — Delete Confirmation Dialog

**Priority:** Medium
**Component:** Frontend — Delete Modal
**Story Points:** 2

**Description:** Confirmation dialog before removing items or clearing the cart.

**API Endpoints:**
- `DELETE /api/v1/cart/delete-item/{itemId}`
- `DELETE /api/v1/cart/delete-items`

**Acceptance Criteria:**
- [ ] Clicking delete on item opens confirmation modal
- [ ] "Clear Cart" opens confirmation modal
- [ ] Modal shows product name or "all items" text
- [ ] If coupon applied on clear cart: modal shows warning + extra confirm checkbox
- [ ] "Confirm" button submits delete
- [ ] "Cancel" closes modal
- [ ] **Loading state:** Spinner on confirm button
- [ ] **Success:** Remove item/cart from UI with toast
- [ ] **Error:** Show error toast, keep modal open

---

## Task 7: Cart — Loading, Empty & Error States

**Priority:** High
**Component:** Frontend — State Handling
**Story Points:** 3

**Description:** Handle all non-happy-path states across the cart page.

**Acceptance Criteria:**
- [ ] **Page loading:** Full page skeleton with item placeholders (3 rows)
- [ ] **Empty cart:** Illustration with "Your cart is empty" and "Browse Products" CTA
- [ ] **Item loading (update):** Quantity input shows spinner during update
- [ ] **Item error (stock):** Revert quantity, show inline "Only X in stock"
- [ ] **Delete error:** Toast "Failed to delete item"
- [ ] **Clear cart error:** Toast "Failed to clear cart", keep items
- [ ] **Network error:** Toast "Network error, please try again"
- [ ] **Rate limit (429):** Toast "Too many requests, please wait"
- [ ] **Auth error (401):** Redirect to login with return URL
- [ ] **Expired cart:** Show warning "Your cart reservation has expired. Items may no longer be available."

---

## Task 8: Cart Responsive Design

**Priority:** Medium
**Component:** Frontend — Responsive
**Story Points:** 3

**Description:** Ensure cart page is fully responsive across mobile, tablet, and desktop.

**Acceptance Criteria:**
- [ ] **Desktop (>1024px):** Full layout with side-by-side items + summary
- [ ] **Tablet (768-1024px):** Stacked layout, full-width summary
- [ ] **Mobile (<768px):** Single column, condensed item cards, bottom-fixed summary bar
- [ ] Quantity selector adapts to touch (larger +/- buttons on mobile)
- [ ] Delete button accessible (swipe-to-delete option on mobile)
- [ ] All modals are full-screen on mobile
- [ ] Cart icon in mobile hamburger menu

---

## Task 9: Cart — Skipped Products Warning (Bulk Add)

**Priority:** Low
**Component:** Frontend — Bulk Add Feedback
**Story Points:** 2

**Description:** When using bulk add (e.g., "Buy Again" or wishlist-to-cart), show warning for skipped products.

**API Endpoint:**
- `POST /api/v1/cart/bulk-items`

**Acceptance Criteria:**
- [ ] If `skipped_product_ids` is non-empty, show a warning banner
- [ ] Banner text: "X products could not be added because they are no longer available"
- [ ] "View details" expandable list shows skipped product names
- [ ] Banner dismissible
- [ ] Do not block the cart page — warning only
