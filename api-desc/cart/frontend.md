# Cart Module — Frontend Integration Guide

## Endpoints

---

### 1. GET /api/v1/cart — List User Cart

**Purpose:** Display the user's shopping cart with all items, totals, and applied promotions.

**Authentication:** Required (Sanctum)

**Response:**
```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "user_id": 3,
        "coupon": null,
        "coupon_code": null,
        "status": "active",
        "reserved_at": "2026-07-28T10:00:00.000000Z",
        "expires_at": "2026-07-31T10:00:00.000000Z",
        "total_items": 3,
        "total_quantity": 5,
        "subtotal": 1499.97,
        "total_price": 1499.97,
        "coupon_discount": 0,
        "total_after_coupon": 1499.97,
        "normal_items_count": 2,
        "fast_items_count": 1,
        "normal_items": [
          {
            "id": 1,
            "product_id": 10,
            "product_variant_id": null,
            "quantity": 2,
            "price": 499.99,
            "total_price": 999.98,
            "attributes": null,
            "shipping_method": "SCHEDULED",
            "promotion_id": null,
            "discount_amount": 0,
            "is_gift": false,
            "product": {
              "id": 10,
              "name": "Wireless Headphones",
              "slug": "wireless-headphones",
              "image": {
                "thumbnail": "https://cdn.example.com/products/thumbnail.jpg"
              }
            }
          }
        ],
        "fast_items": [],
        "has_eligible_promotion": false
      }
    ]
  }
}
```

---

### 2. POST /api/v1/cart — Add Item to Cart

**Purpose:** Add a product to the user's shopping cart.

**Authentication:** Required (Sanctum)

**Request:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| item[product_id] | int | Yes | Product ID |
| item[quantity] | int | Yes | Quantity (min: 1) |
| item[product_variant_id] | int | No | Variant ID for variable products |
| item[shipping_method] | string | Yes | `SCHEDULED` or `FAST` |

**Response (201):**
```json
{
  "status": 201,
  "message": "Cart created successfully",
  "success": true,
  "data": { "id": 1, "user_id": 3, "status": "active", "total_price": 999.98 }
}
```

**Error Response (400 — stock exceeded):**
```json
{
  "status": 400,
  "message": "Quantity exceeds available stock",
  "success": false
}
```

---

### 3. PUT /api/v1/cart/update-item — Update Item Quantity

**Purpose:** Update an item's quantity (set mode - absolute value, not incremental).

**Authentication:** Required (Sanctum)

**Request:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| item[product_id] | int | Yes | Product ID to update |
| item[quantity] | int | Yes | New absolute quantity |
| item[shipping_method] | string | No | If omitted, preserves existing |

**Response (200):**
```json
{
  "status": 200,
  "message": "Cart updated successfully",
  "success": true,
  "data": { "id": 1, "total_price": 2499.95 }
}
```

---

### 4. DELETE /api/v1/cart/delete-item/{itemId} — Remove Item

**Purpose:** Remove a single item from the cart.

**Authentication:** Required (Sanctum)

**Response (200):**
```json
{ "status": 200, "message": "Cart item deleted successfully", "success": true }
```

---

### 5. DELETE /api/v1/cart/delete-items — Clear Cart

**Purpose:** Remove all items from the cart.

**Authentication:** Required (Sanctum)

**Request (optional):**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| confirm | boolean | If coupon applied | `true` to confirm deletion with coupon |

**Response (200):**
```json
{ "status": 200, "message": "Cart deleted successfully", "success": true }
```

**Warning Response (400 — coupon without confirm):**
```json
{
  "status": 400,
  "message": "This cart has a coupon applied. Please confirm to proceed with deletion.",
  "success": false
}
```

---

### 6. POST /api/v1/cart/bulk-items — Bulk Add Items

**Purpose:** Add multiple items to the cart in one request.

**Authentication:** Required (Sanctum)

**Request:**
```json
{
  "items": [
    { "product_id": 10, "quantity": 2, "shipping_method": "SCHEDULED" },
    { "product_id": 15, "quantity": 1, "shipping_method": "FAST" }
  ]
}
```

**Response (200):**
```json
{
  "status": 200,
  "message": "Cart created successfully",
  "success": true,
  "data": { "id": 1, "skipped_product_ids": [] }
}
```

---

## Frontend Usage

### Loading State
```js
const response = await fetch('/api/v1/cart', {
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' }
});
if (!response.ok) {
  // Show error state
}
const cart = await response.json();
```

### Empty State
- **No items in cart:** Items arrays will be empty `[]` — show "Your cart is empty" with a "Start Shopping" CTA

### Error State
- **401:** Token expired or missing — redirect to login
- **403:** Accessing another user's cart — shouldn't happen with proper auth
- **400:** Business logic error (stock exceeded, coupon warning) — show message in toast
- **422:** Validation errors — show field-level error messages
- **429:** Rate limit exceeded — show "Too many requests, please wait"

---

## Key Considerations

1. **Shipping method split** — Items are divided into `normal_items` (SCHEDULED) and `fast_items` (FAST). The frontend should display them in separate sections with different delivery timelines.

2. **Price snapshotting** — Prices are captured when the item is added to the cart and do not change if the product's price changes later. Display the cart price as-is; do not re-fetch product prices.

3. **Inventory reservation** — Adding an item reserves stock for 3 days. The cart `expires_at` field shows when the reservation expires. Display a countdown timer if needed.

4. **Coupon confirmation** — If the cart has a coupon applied, clearing the cart requires `"confirm": true` in the request body. Show a confirmation dialog.

5. **Gift items** — `is_gift: true` items are free products from promotions. They have `price: 0` and `total_price: 0`. Display them separately with a "FREE" badge.

6. **Bulk add skip** — `skipped_product_ids` in the bulk response lists products that were skipped (soft-deleted or non-existent). Show a warning message for skipped items.

7. **Response nesting** — Cart data is nested in `data.data[0]` because the index endpoint is paginated. For the single cart view, use `data` directly.

8. **Float precision** — All monetary values (`price`, `total_price`, `subtotal`, `coupon_discount`, `total_after_coupon`) are rounded to 2 decimal places by the API.
