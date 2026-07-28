# API Reference — Cart

---

## Endpoints

---

### GET /api/v1/cart

List authenticated user's carts (paginated).

**Authentication**: `auth:sanctum`

**Query Parameters**:

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| page | int | 1 | Page number |
| per_page | int | 15 | Items per page (alias: limit) |

**Response 200**:
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
        "coupon": {
          "id": 5,
          "code": "SAVE10",
          "type": "percentage",
          "discount": 10,
          "max_discount_amount": 5000,
          "minimum_cart_amount": 1000
        },
        "coupon_code": "SAVE10",
        "status": "active",
        "reserved_at": "2026-07-28T10:00:00.000000Z",
        "expires_at": "2026-07-31T10:00:00.000000Z",
        "total_items": 3,
        "total_quantity": 5,
        "subtotal": 1499.97,
        "total_price": 1499.97,
        "coupon_discount": 149.99,
        "total_after_coupon": 1349.98,
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
        "fast_items": [
          {
            "id": 3,
            "product_id": 25,
            "product_variant_id": null,
            "quantity": 1,
            "price": 499.99,
            "total_price": 499.99,
            "attributes": null,
            "shipping_method": "FAST",
            "promotion_id": null,
            "discount_amount": 0,
            "is_gift": false,
            "product": {
              "id": 25,
              "name": "USB-C Cable",
              "slug": "usb-c-cable",
              "image": {
                "thumbnail": "https://cdn.example.com/products/thumbnail.jpg"
              }
            }
          }
        ],
        "has_eligible_promotion": false
      }
    ],
    "page": 1,
    "current_page": 1,
    "from": 1,
    "to": 15,
    "last_page": 1,
    "path": "http://localhost:8000/api/v1/cart",
    "per_page": 15,
    "total": 1,
    "next_page_url": null,
    "prev_page_url": null,
    "last_page_url": "http://localhost:8000/api/v1/cart?page=1",
    "first_page_url": "http://localhost:8000/api/v1/cart?page=1"
  }
}
```

**Quick Test**:
```bash
# List user carts
curl -X GET "http://localhost:8000/api/v1/cart?page=1&per_page=15" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Business Rules**:
- Returns only carts belonging to the authenticated user (filtered by `user_id`)
- Eager loads: `items.product`, `items.productVariant.attributeProducts.attributeValue.attribute`
- `coupon` field is an object (CouponResource) only if a coupon code is applied; otherwise null
- `coupon_discount` and `total_after_coupon` are calculated dynamically from the coupon, not stored
- Items are split into `normal_items` (SCHEDULED) and `fast_items` (FAST) shipping methods
- `has_eligible_promotion` is computed via `PromotionService::hasEligiblePromotion()`

---

### POST /api/v1/cart

Add an item to the cart.

**Authentication**: `auth:sanctum`

**Request Body**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| item | object | required | Item data container |
| item.product_id | int | required | Valid product ID |
| item.quantity | int | required | Quantity (min: 1) |
| item.product_variant_id | int | sometimes | Valid variant ID (for variable products) |
| item.attributes | array | sometimes | Custom attribute values |
| item.shipping_method | string | required | `SCHEDULED` or `FAST` |

**Validation Rules**:

| Field | Rules |
|-------|-------|
| item | required, array, min:1 |
| item.product_id | required, integer, exists:products,id |
| item.quantity | required, integer, min:1 |
| item.product_variant_id | sometimes, nullable, integer, exists:product_variants,id |
| item.attributes | sometimes, array |
| item.shipping_method | required, string, in:SCHEDULED,FAST,scheduled,fast |

**Request Body (JSON)**:
```json
{
  "item": {
    "product_id": 10,
    "quantity": 2,
    "shipping_method": "SCHEDULED"
  }
}
```

**Response 201**:
```json
{
  "status": 201,
  "message": "Cart created successfully",
  "success": true,
  "data": {
    "id": 1,
    "user_id": 3,
    "status": "active",
    "total_price": 999.98,
    "normal_items_count": 1,
    "fast_items_count": 0,
    "normal_items": [],
    "fast_items": [],
    "subtotal": 999.98
  }
}
```

**Response 400** (business error):
```json
{
  "status": 400,
  "message": "Quantity exceeds available stock",
  "success": false
}
```

**Response 422** (validation):
```json
{
  "item.quantity": ["The item.quantity must be at least 1."],
  "item.shipping_method": ["The selected item.shipping_method is invalid."]
}
```

**Quick Test**:
```bash
# Add a simple product to cart
curl -X POST "http://localhost:8000/api/v1/cart" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"item": {"product_id": 10, "quantity": 2, "shipping_method": "SCHEDULED"}}'

# Add a variant product to cart
curl -X POST "http://localhost:8000/api/v1/cart" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"item": {"product_id": 15, "product_variant_id": 3, "quantity": 1, "shipping_method": "FAST"}}'
```

**Business Rules**:
- Uses `DB::transaction` with `lockForUpdate` on the cart and inventory rows
- Prices are **snapshotted** at reservation time (not recalculated at checkout)
- Adding the same product+variant+shipping method increments the existing item's quantity
- On first item added, creates a new cart if none exists for the user
- Inventory is reserved (`reserved_quantity += delta`) immediately
- Cart receives a 3-day TTL (`expires_at = now() + 3 days`)
- Any existing promotion on the cart is cleared on item change
- FAST shipping requires `product.is_fast_shipping_available === true`

---

### GET /api/v1/cart/{id}

Get a specific cart by ID.

**Authentication**: `auth:sanctum`

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| id | int | Cart ID (numeric only, validated by `whereNumber`) |

**Response 200**:
```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": {
    "id": 1,
    "user_id": 3,
    "status": "active",
    "total_price": 999.98,
    "subtotal": 999.98,
    "normal_items": [],
    "fast_items": []
  }
}
```

**Response 403**:
```json
{
  "status": 403,
  "message": "Not authorized",
  "success": false
}
```

**Response 404**:
```json
{
  "status": 404,
  "message": "Not found",
  "success": false
}
```

**Quick Test**:
```bash
# Show cart by ID
curl -X GET "http://localhost:8000/api/v1/cart/1" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Business Rules**:
- Authorization: the cart's `user_id` must match the authenticated user
- Returns `403` (AuthorizationException) on mismatch, not 404

---

### PUT /api/v1/cart/update-item

Update an item's quantity (set mode — absolute value, not incremental).

**Authentication**: `auth:sanctum`

**Request Body**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| item | object | required | Item data container |
| item.product_id | int | required | Product ID to update |
| item.quantity | int | required | New absolute quantity (min: 1) |
| item.product_variant_id | int | sometimes | Variant ID (for variable products) |
| item.attributes | array | sometimes | Custom attributes |
| item.shipping_method | string | sometimes | If omitted, preserves existing method |

**Validation Rules**:

| Field | Rules |
|-------|-------|
| item | required, array, min:1 |
| item.product_id | required_with:item, integer, exists:products,id |
| item.quantity | required_with:item, integer, min:1 |
| item.product_variant_id | sometimes, nullable, integer, exists:product_variants,id |
| item.attributes | sometimes, array |
| item.shipping_method | sometimes, string, in:SCHEDULED,FAST,scheduled,fast |

**Request Body (JSON)**:
```json
{
  "item": {
    "product_id": 10,
    "quantity": 5
  }
}
```

**Response 200**:
```json
{
  "status": 200,
  "message": "Cart updated successfully",
  "success": true,
  "data": {
    "id": 1,
    "user_id": 3,
    "status": "active",
    "total_price": 2499.95,
    "subtotal": 2499.95
  }
}
```

**Response 400** (business error):
```json
{
  "status": 400,
  "message": "Quantity exceeds available stock",
  "success": false
}
```

**Quick Test**:
```bash
# Update item quantity to 5
curl -X PUT "http://localhost:8000/api/v1/cart/update-item" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"item": {"product_id": 10, "quantity": 5}}'
```

**Business Rules**:
- Quantity is **set** (absolute), not added (incremental) — unlike POST
- If `shipping_method` is omitted, the existing item's shipping method is preserved
- Adjusts inventory delta (if quantity increases → reserve more; if decreases → release excess)
- Promotion is cleared on any item update

---

### DELETE /api/v1/cart/delete-item/{itemId}

Remove a single item from the cart.

**Authentication**: `auth:sanctum`

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| itemId | int | Cart item ID |

**Response 200**:
```json
{
  "status": 200,
  "message": "Cart item deleted successfully",
  "success": true
}
```

**Response 400**:
```json
{
  "status": 400,
  "message": "Failed to delete cart item",
  "success": false
}
```

**Quick Test**:
```bash
# Delete cart item with ID 1
curl -X DELETE "http://localhost:8000/api/v1/cart/delete-item/1" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Business Rules**:
- Releases reserved inventory back to available stock
- If the deleted item was the last item, the coupon is cleared from the cart
- Promotion data is revalidated after deletion
- Cart `total_price` is recalculated as sum of remaining items

---

### DELETE /api/v1/cart/delete-items

Clear the entire cart.

**Authentication**: `auth:sanctum`

**Request Body** (optional):

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| confirm | boolean | sometimes | Required if cart has a coupon applied |

**Response 200**:
```json
{
  "status": 200,
  "message": "Cart deleted successfully",
  "success": true
}
```

**Response 400** (warning — coupon applied without confirm):
```json
{
  "status": 400,
  "message": "This cart has a coupon applied. Please confirm to proceed with deletion.",
  "success": false
}
```

**Response 404**:
```json
{
  "status": 404,
  "message": "Cart not found",
  "success": false
}
```

**Quick Test**:
```bash
# Clear cart (no coupon — works directly)
curl -X DELETE "http://localhost:8000/api/v1/cart/delete-items" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"

# Clear cart with coupon — requires confirm
curl -X DELETE "http://localhost:8000/api/v1/cart/delete-items" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"confirm": true}'
```

**Business Rules**:
- Releases ALL reserved inventory back to available stock
- Deletes all cart items
- If cart has a coupon applied and `confirm` is not `true`, returns warning instead of deleting
- Resets cart `total_price` to 0

---

### POST /api/v1/cart/bulk-items

Bulk add multiple items to the cart.

**Authentication**: `auth:sanctum`

**Request Body**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| items | array | required | Array of item objects |
| items.*.product_id | int | required | Valid product ID |
| items.*.quantity | int | required | Quantity (min: 1) |
| items.*.product_variant_id | int | sometimes | Variant ID |
| items.*.shipping_method | string | required | `SCHEDULED` or `FAST` |

**Validation Rules**:

| Field | Rules |
|-------|-------|
| items | required, array |
| items.*.product_id | required, integer |
| items.*.quantity | required, integer, min:1 |
| items.*.product_variant_id | nullable, integer |
| items.*.shipping_method | required, string, in:scheduled,fast,SCHEDULED,FAST |

**Request Body (JSON)**:
```json
{
  "items": [
    { "product_id": 10, "quantity": 2, "shipping_method": "SCHEDULED" },
    { "product_id": 15, "product_variant_id": 3, "quantity": 1, "shipping_method": "FAST" }
  ]
}
```

**Response 200**:
```json
{
  "status": 200,
  "message": "Cart created successfully",
  "success": true,
  "data": {
    "id": 1,
    "user_id": 3,
    "status": "active",
    "skipped_product_ids": []
  }
}
```

**Response 200** (with skipped products):
```json
{
  "status": 200,
  "message": "Cart created successfully",
  "success": true,
  "data": {
    "id": 1,
    "user_id": 3,
    "status": "active",
    "skipped_product_ids": [99, 100]
  }
}
```

**Quick Test**:
```bash
# Bulk add items
curl -X POST "http://localhost:8000/api/v1/cart/bulk-items" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"items": [{"product_id": 10, "quantity": 2, "shipping_method": "SCHEDULED"}, {"product_id": 15, "quantity": 1, "shipping_method": "FAST"}]}'
```

**Business Rules**:
- Products that are soft-deleted or don't exist are silently skipped
- `skipped_product_ids` in response lists all skipped product IDs
- Runs in a `DB::transaction` — all valid items are added atomically
- Each valid item is processed individually through the same `storeCart()` flow
