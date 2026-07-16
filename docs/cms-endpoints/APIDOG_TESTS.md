# Two-Section Cart - API Testing Guide

## Meem Commerce (Marvel)

---

## Prerequisites

1. Run all migrations:
```bash
php artisan migrate
```

2. Run `composer dump-autoload` to register new classes.

3. Ensure you have:
   - A test user with auth token (for auth-required endpoints)
   - At least one simple product with `is_fast_shipping_available = true`
   - At least one simple product with `is_fast_shipping_available = false`
   - At least one variable product (optional, for variant tests)
   - A valid coupon code (for coupon tests)
   - An active governorate with `is_fast_shipping_enabled = true`

4. Before each test run, clear the cart:
```
DELETE /api/v1/cart/delete-items
Authorization: Bearer {{TOKEN}}
```

---

## Test Suite

---

### Test 1: Add Item to Cart — SCHEDULED (Default)

**Endpoint:** `POST /api/v1/cart`

**Auth:** Customer (Bearer token) — middleware: `auth:sanctum`

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `item.product_id` | integer | Yes | Must exist in `products` table |
| `item.quantity` | integer | Yes | Minimum 1 |
| `item.shipping_method` | string | No | `SCHEDULED` or `FAST` (default: `SCHEDULED`) |

**Example Request Body:**
```json
{
    "item": {
        "product_id": 1,
        "quantity": 2,
        "shipping_method": "SCHEDULED"
    }
}
```

**Expected Response (201):**
```json
{
    "success": true,
    "message": "Cart created successfully",
    "data": {
        "id": 1,
        "status": "active",
        "total_items": 1,
        "total_quantity": 2,
        "normal_items_count": 1,
        "fast_items_count": 0,
        "normal_items": [
            {
                "id": 1,
                "shipping_method": "SCHEDULED",
                "quantity": 2,
                "price": 100.00,
                "total_price": 200.00,
                "product": {
                    "id": 1,
                    "name": "Product Name",
                    "slug": "product-name"
                }
            }
        ],
        "fast_items": []
    }
}
```

**Verify:**
- `normal_items_count` = 1, `fast_items_count` = 0
- Item has `shipping_method = "SCHEDULED"`
- Item appears in `normal_items` array

**Negative Test:** Omit `shipping_method` entirely:
- Expected: defaults to `"SCHEDULED"`, item appears in `normal_items`

---

### Test 2: Add Item to Cart — FAST (Eligible Product)

**Endpoint:** `POST /api/v1/cart`

**Auth:** Customer (Bearer token) — middleware: `auth:sanctum`

**Prerequisites:**
- Product has `is_fast_shipping_available = true`

**Request Body:**
```json
{
    "item": {
        "product_id": 1,
        "quantity": 3,
        "shipping_method": "FAST"
    }
}
```

**Expected Response (201):**
```json
{
    "success": true,
    "data": {
        "normal_items_count": 0,
        "fast_items_count": 1,
        "fast_items": [
            {
                "id": 2,
                "shipping_method": "FAST",
                "quantity": 3
            }
        ]
    }
}
```

**Verify:**
- `normal_items_count` = 0, `fast_items_count` = 1
- Item has `shipping_method = "FAST"`
- Item appears in `fast_items` array

---

### Test 3: Add Item to Cart — FAST (Ineligible Product) — SHOULD FAIL

**Endpoint:** `POST /api/v1/cart`

**Auth:** Customer (Bearer token) — middleware: `auth:sanctum`

**Prerequisites:**
- Product has `is_fast_shipping_available = false`

**Request Body:**
```json
{
    "item": {
        "product_id": 2,
        "quantity": 1,
        "shipping_method": "FAST"
    }
}
```

**Expected Response (400):**
```json
{
    "success": false,
    "message": "One or more products are not eligible for fast shipping"
}
```

**Verify:**
- Item is NOT added to cart
- Cart remains unchanged

---

### Test 4: Add Item with Invalid Shipping Method — SHOULD FAIL

**Endpoint:** `POST /api/v1/cart`

**Auth:** Customer (Bearer token) — middleware: `auth:sanctum`

**Request Body:**
```json
{
    "item": {
        "product_id": 1,
        "quantity": 1,
        "shipping_method": "INVALID"
    }
}
```

**Expected Response (422):**
```json
{
    "item.shipping_method": ["The selected item.shipping_method is invalid."]
}
```

---

### Test 5: Add FAST Variable Product with Variant

**Endpoint:** `POST /api/v1/cart`

**Auth:** Customer (Bearer token) — middleware: `auth:sanctum`

**Prerequisites:**
- Variable product has `is_fast_shipping_available = true`
- Variant is in stock

**Request Body:**
```json
{
    "item": {
        "product_id": 3,
        "product_variant_id": 10,
        "quantity": 1,
        "shipping_method": "FAST"
    }
}
```

**Expected Response (201):**
```json
{
    "success": true,
    "data": {
        "fast_items": [
            {
                "id": 3,
                "product_variant_id": 10,
                "shipping_method": "FAST",
                "attributes": [
                    {
                        "attribute": "Color",
                        "value": "Red"
                    }
                ]
            }
        ]
    }
}
```

**Verify:**
- Item appears in `fast_items` with variant attributes
- `product_variant_id` matches requested variant

---

### Test 6: Get Authenticated User's Cart

**Endpoint:** `GET /api/v1/cart`

**Auth:** Customer (Bearer token) — middleware: `auth:sanctum`

**Query Parameters:**

| Parameter | Type | Required | Default | Max | Description |
|-----------|------|----------|---------|-----|-------------|
| `limit` | integer | No | 15 | 100 | Items per page |
| `page` | integer | No | 1 | — | Page number |

**Expected Response (200):**
```json
{
    "success": true,
    "data": {
        "data": [
            {
                "id": 1,
                "user_id": 5,
                "coupon": null,
                "status": "active",
                "reserved_at": "2026-06-23T10:00:00+00:00",
                "expires_at": "2026-06-26T10:00:00+00:00",
                "total_items": 4,
                "total_quantity": 6,
                "total_price": 500.00,
                "normal_items_count": 2,
                "fast_items_count": 2,
                "normal_items": [
                    {
                        "id": 1,
                        "shipping_method": "SCHEDULED",
                        "quantity": 2,
                        "price": 100.00,
                        "total_price": 200.00,
                        "product": {
                            "id": 1,
                            "name": "Product Name",
                            "slug": "product-name"
                        }
                    }
                ],
                "fast_items": [
                    {
                        "id": 2,
                        "shipping_method": "FAST",
                        "quantity": 3,
                        "price": 100.00,
                        "total_price": 300.00,
                        "product": {
                            "id": 1,
                            "name": "Product Name",
                            "slug": "product-name"
                        }
                    }
                ]
            }
        ],
        "total": 1,
        "last_page": 1,
        "current_page": 1,
        "per_page": 15
    }
}
```

**Verify:**
- Response contains `normal_items` and `fast_items` arrays
- Response contains `normal_items_count` and `fast_items_count`
- Response contains shared `coupon` field
- Every item in `normal_items` has `shipping_method = "SCHEDULED"`
- Every item in `fast_items` has `shipping_method = "FAST"`
- `normal_items_count + fast_items_count == total_items`
- Each item has `product` object with `id`, `name`, `slug`, `thumbnail`
- When cart has no items: `total_items` is null, arrays are empty

---

### Test 7: Normal Checkout — Processes Only SCHEDULED Items

**Prerequisites:**
- Cart has at least one SCHEDULED item AND at least one FAST item
- Products are in stock
- Cart reservation is valid

**Endpoint:** `POST /api/v1/general/checkout`

**Auth:** Customer (Bearer token) — middleware: `auth:sanctum`, `check-email`

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Customer full name |
| `user_phone` | string | Yes | Customer phone number |
| `user_email` | email | Yes | Customer email |
| `address` | object | Yes | Shipping address |
| `notes` | string | No | Delivery notes |
| `selected_promotion_id` | integer | No | Must exist in `promotions` table |
| `selected_gift_product_id` | integer | No | Must exist in `products` table |

**Example Request Body:**
```json
{
    "name": "Test User",
    "user_phone": "01000000000",
    "user_email": "test@example.com",
    "address": {
        "address": "123 Street",
        "city": "Cairo",
        "country": "Egypt"
    },
    "notes": "Test normal checkout"
}
```

**Expected Response (200):**
```json
{
    "success": true,
    "message": "Checkout successful",
    "data": {
        "url": "https://myfatoorah.com/payment/..."
    }
}
```

**Verify After Payment Callback Success:**
- `GET /api/v1/cart` → `normal_items_count = 0` (SCHEDULED items removed)
- `fast_items_count` unchanged (FAST items remain)
- Cart `status` is still `"active"` (because FAST items remain)
- Order created with only SCHEDULED items (no FAST items in order_items)
- Order has `shipping_method = null` (normal shipping)
- FAST items remain reserved in cart

**Verify After Payment Failure:**
- Cart is released (all items still present, reservation cleared)
- Order status = `"cancelled"`

---

### Test 8: Normal Checkout with Only FAST Items — SHOULD FAIL

**Prerequisites:**
- Cart has FAST items but NO SCHEDULED items

**Endpoint:** `POST /api/v1/general/checkout`

**Auth:** Customer (Bearer token) — middleware: `auth:sanctum`, `check-email`

**Expected Response (400):**
```json
{
    "success": false,
    "message": "Cart not found"
}
```

**Verify:**
- No order is created
- FAST items remain in cart

---

### Test 9: Fast Checkout — Processes Only FAST Items

**Prerequisites:**
- Cart has at least one SCHEDULED item AND at least one FAST item
- Fast shipping globally enabled (via admin settings)
- Within working hours (default 08:00 - 22:00)
- Governorate has `is_fast_shipping_enabled = true`
- All FAST items have `is_fast_shipping_available = true`
- Products are in stock

**Endpoint:** `POST /api/v1/general/checkout/fast`

**Auth:** Customer (Bearer token) — middleware: `auth:sanctum`

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Customer full name (max 255) |
| `user_phone` | string | Yes | Customer phone number (max 255) |
| `user_email` | email | Yes | Customer email (max 255) |
| `address` | object | Yes | Shipping address |
| `notes` | string | No | Delivery notes |
| `governorate_id` | integer | Yes | Must exist in `governorates` table |
| `selected_promotion_id` | integer | No | Must exist in `promotions` table |
| `selected_gift_product_id` | integer | No | Must exist in `products` table |

**Example Request Body:**
```json
{
    "name": "Test User",
    "user_phone": "01000000000",
    "user_email": "test@example.com",
    "address": {
        "address": "123 Street",
        "city": "Cairo",
        "country": "Egypt"
    },
    "notes": "Fast checkout test",
    "governorate_id": 1
}
```

**Expected Response (200):**
```json
{
    "success": true,
    "message": "Checkout successful",
    "data": {
        "url": "https://myfatoorah.com/payment/..."
    }
}
```

**Verify After Payment Callback Success:**
- `GET /api/v1/cart` → `fast_items_count = 0` (FAST items removed)
- `normal_items_count` unchanged (SCHEDULED items remain)
- Cart `status` is still `"active"` (because SCHEDULED items remain)
- Order created with `shipping_method = "FAST"`
- Order has `expected_delivery_at` and `fast_shipping_fee`
- SCHEDULED items remain reserved in cart

**Verify After Payment Failure:**
- Cart is released (all items still present)
- Order status = `"cancelled"`

---

### Test 10: Fast Checkout with Only SCHEDULED Items — SHOULD FAIL

**Prerequisites:**
- Cart has SCHEDULED items but NO FAST items

**Endpoint:** `POST /api/v1/general/checkout/fast`

**Auth:** Customer (Bearer token) — middleware: `auth:sanctum`

**Request Body:**
```json
{
    "name": "Test User",
    "user_phone": "01000000000",
    "user_email": "test@example.com",
    "address": {
        "address": "123 Street",
        "city": "Cairo",
        "country": "Egypt"
    },
    "governorate_id": 1
}
```

**Expected Response (422):**
```json
{
    "success": false,
    "message": "No fast shipping items in cart."
}
```

**Verify:**
- No order is created
- SCHEDULED items remain in cart

---

### Test 11: Coupon — Normal Checkout Consumes It First

**Prerequisites:**
- Cart has both SCHEDULED and FAST items
- Valid coupon code exists

**Step 1 — Apply Coupon:**
```
POST /api/v1/general/coupons/apply
Authorization: Bearer {{TOKEN}}
Content-Type: application/json

{
    "code": "SUMMER20"
}
```

**Step 2 — Verify coupon on cart:**
```
GET /api/v1/cart
```
→ `coupon` field is set to `"SUMMER20"`

**Step 3 — Do normal checkout:**
```
POST /api/v1/general/checkout
Authorization: Bearer {{TOKEN}}
{
    "name": "Test User",
    "user_phone": "01000000000",
    "user_email": "test@example.com",
    "address": {"address":"123 Street","city":"Cairo","country":"Egypt"}
}
```

**Step 4 — After payment callback success, verify:**

**Expected:**
- Order's `coupon` = `"SUMMER20"` (coupon recorded on order)
- `GET /api/v1/cart` → `coupon = null` (coupon consumed, cleared from cart)
- FAST items still in cart (SCHEDULED items removed)
- CouponUsage table has entry for this user + coupon

**Step 5 — Do fast checkout on remaining FAST items:**
```
POST /api/v1/general/checkout/fast
```
→ Fast order has NO coupon discount (coupon already consumed)

---

### Test 12: Coupon — Fast Checkout Consumes It First

**Prerequisites:**
- Cart has both SCHEDULED and FAST items
- Valid coupon code exists

**Step 1 — Apply Coupon:**
```
POST /api/v1/general/coupons/apply
{
    "code": "SUMMER20"
}
```

**Step 2 — Verify coupon on cart:**
→ `coupon` field is set

**Step 3 — Do fast checkout:**
```
POST /api/v1/general/checkout/fast
{
    "name": "Test User",
    "user_phone": "01000000000",
    "user_email": "test@example.com",
    "address": {"address":"123 Street","city":"Cairo","country":"Egypt"},
    "governorate_id": 1
}
```

**Step 4 — After payment callback success, verify:**

**Expected:**
- Fast order's `coupon` = `"SUMMER20"`
- `GET /api/v1/cart` → `coupon = null` (consumed)
- SCHEDULED items still in cart (FAST items removed)

**Step 5 — Do normal checkout on remaining SCHEDULED items:**
```
POST /api/v1/general/checkout
```
→ Normal order has NO coupon discount (already consumed)

---

### Test 13: Delete Single Cart Item

**Endpoint:** `DELETE /api/v1/cart/delete-item/{itemId}`

**Auth:** Customer (Bearer token) — middleware: `auth:sanctum`

**Path Parameter:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `itemId` | integer | Yes | Cart item ID to delete |

**Expected Response (200):**
```json
{
    "success": true,
    "message": "Cart item deleted successfully"
}
```

**Verify:**
- If deleted item was SCHEDULED → `normal_items_count` decreased by 1
- If deleted item was FAST → `fast_items_count` decreased by 1
- Cart `total_price` recalculated correctly
- Inventory reservation released for that item

**Negative Test:** Delete non-existent item:
```
DELETE /api/v1/cart/delete-item/99999
```
Expected: 400 — `"Cart item delete failed"`

---

### Test 14: Clear Entire Cart

**Endpoint:** `DELETE /api/v1/cart/delete-items`

**Auth:** Customer (Bearer token) — middleware: `auth:sanctum`

**Expected Response (200):**
```json
{
    "success": true,
    "message": "Cart deleted successfully"
}
```

**Verify:**
- `GET /api/v1/cart` → `total_items` is null, `normal_items` and `fast_items` are empty
- OR cart no longer returned (404)
- All inventory reservations released

---

## Edge Cases

---

### Edge Case 1: Same Product in Both Sections

**Scenario:**
1. Add Product A to cart with `shipping_method = "SCHEDULED"` (qty 1)
2. Add Product A to cart with `shipping_method = "FAST"` (qty 2)

**Expected:**
- Product A appears in BOTH `normal_items` (qty 1) and `fast_items` (qty 2)
- Total items = 2 (two separate cart item rows)
- Total quantity = 3

**Verify:**
- Each section has its own cart item row
- Quantities are independent per section

---

### Edge Case 2: Duplicate Product in Same Section (Merge)

**Scenario:**
1. Add Product A with `shipping_method = "SCHEDULED"` (qty 1)
2. Add Product A with `shipping_method = "SCHEDULED"` (qty 2)

**Expected:**
- Single cart item row in `normal_items` with quantity = 3 (merged)
- `normal_items_count = 1`

**Verify:**
- Items merge within the same section
- Item stays separate between different sections

---

### Edge Case 3: Checkout After Product Becomes Ineligible

**Scenario:**
1. Add Product A to cart with `shipping_method = "FAST"` (eligible at time of add)
2. Admin sets `is_fast_shipping_available = false` for Product A
3. Try fast checkout

**Expected (422):**
```json
{
    "success": false,
    "message": "Fast shipping is not available for one or more products"
}
```

**Verify:**
- Checkout rejected
- Cart items remain unchanged

---

### Edge Case 4: Checkout After All Items Removed

**Scenario:**
1. Add items to cart
2. Delete all items individually via `DELETE /cart/delete-item/{id}`
3. Try checkout (normal or fast)

**Expected:**
- Normal checkout: 400 — `"Cart not found"`
- Fast checkout: 400 — `"Cart not found"`

---

### Edge Case 5: Coupon Expires Between Apply and Checkout

**Scenario:**
1. Apply valid coupon to cart
2. Coupon expires (admin changes validity or end date passes)
3. Try checkout

**Expected:**
- Checkout proceeds without coupon discount
- Coupon remains on cart (or is silently ignored)
- No error thrown for expired coupon

---

### Edge Case 6: Empty Cart After Second Checkout

**Scenario:**
1. Cart has both SCHEDULED and FAST items
2. Normal checkout succeeds → SCHEDULED items removed, FAST items remain
3. Fast checkout succeeds → FAST items removed, cart is now empty

**Expected:**
- After step 2: cart `status = "active"`, only FAST items remain
- After step 3: cart `status = "checked_out"`, all items gone
- `GET /api/v1/cart` returns empty/null cart

---

## Validation Rules

### CartCreateRequest

| Field | Rules |
|-------|-------|
| `item` | required, array, min:1 |
| `item.product_id` | required, integer, exists:products,id |
| `item.quantity` | required, integer, min:1 |
| `item.product_variant_id` | sometimes, nullable, integer, exists:product_variants,id |
| `item.attributes` | sometimes, array |
| `item.shipping_method` | sometimes, string, in:SCHEDULED,FAST |

### OrderCreateRequest (Normal Checkout)

| Field | Rules |
|-------|-------|
| `name` | required, string, max:255 |
| `user_phone` | required, string, max:255 |
| `user_email` | required, email, max:255 |
| `address` | required, array |
| `notes` | nullable, string |
| `selected_promotion_id` | nullable, integer, exists:promotions,id |
| `selected_gift_product_id` | nullable, integer, exists:products,id |

### FastCheckoutRequest

| Field | Rules |
|-------|-------|
| `name` | required, string, max:255 |
| `user_phone` | required, string, max:255 |
| `user_email` | required, email, max:255 |
| `address` | required, array |
| `notes` | nullable, string |
| `governorate_id` | required, integer, exists:governorates,id |
| `selected_promotion_id` | nullable, integer, exists:promotions,id |
| `selected_gift_product_id` | nullable, integer, exists:products,id |

---

## Database Verification Queries

```sql
-- Check cart items by shipping method
SELECT ci.id, ci.product_id, ci.shipping_method, ci.quantity, ci.total_price
FROM cart_items ci
JOIN carts c ON c.id = ci.cart_id
WHERE c.user_id = {user_id};

-- Check if coupon was consumed
SELECT c.id, c.coupon, c.user_id
FROM carts c
WHERE c.user_id = {user_id};

-- Check coupon usage record
SELECT cu.coupon_id, cu.user_id, cu.order_id, cu.used_at
FROM coupon_usages cu
WHERE cu.user_id = {user_id};

-- Check order shipping method
SELECT o.id, o.shipping_method, o.coupon, o.total_price, o.status
FROM orders o
WHERE o.user_id = {user_id}
ORDER BY o.id DESC
LIMIT 5;

-- Check order items for a specific order
SELECT oi.product_id, oi.product_quantity, oi.product_total_price
FROM order_items oi
WHERE oi.order_id = {order_id};
```

---

## Endpoints Summary

| # | Method | URL | Auth | Purpose |
|---|--------|-----|------|---------|
| 1 | `POST` | `/api/v1/cart` | Sanctum | Add item with optional `shipping_method` |
| 6 | `GET` | `/api/v1/cart` | Sanctum | Get cart with sections |
| 7 | `POST` | `/api/v1/general/checkout` | Sanctum | Checkout only SCHEDULED items |
| 9 | `POST` | `/api/v1/general/checkout/fast` | Sanctum | Checkout only FAST items |
| 13 | `DELETE` | `/api/v1/cart/delete-item/{itemId}` | Sanctum | Delete single cart item |
| 14 | `DELETE` | `/api/v1/cart/delete-items` | Sanctum | Clear entire cart |

---

## Rollback Plan

If rollback is needed, revert the code changes from commit/PR that introduced the two-section cart refactoring. No database migrations are needed — the `cart_items.shipping_method` column already exists from a previous migration.
