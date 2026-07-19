# Product Module — API Reference (CRUD Only)

---

## GET /products

List paginated products with search, filter, sort.

**Auth:** Public (no token required for index+show)

**Permissions:** none for public; `view-products` for authenticated scope

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `limit` | integer | No | Per page (default: 15) |
| `search` | string | No | Search name, description, sku |
| `orderBy` | string | No | `id`, `name`, `price`, `created_at`, `sold_quantity` |
| `orderDir` | string | No | `asc`, `desc` (default: `desc`) |
| `category` | string | No | Category slug |
| `shop_id` | integer | No | Filter by shop |
| `type_id` | integer | No | Filter by product type |
| `brand` | string | No | Brand slug |
| `min_price` | float | No | Minimum price |
| `max_price` | float | No | Maximum price |
| `tags` | string | No | Comma-separated tag slugs (AND logic) |

### Response 200

```json
{
  "success": true,
  "message": "MESSAGE.FETCH_DATA_SUCCESSFULLY",
  "data": {
    "data": [
      {
        "id": 1,
        "name": "T-Shirt",
        "slug": "t-shirt",
        "description": "A comfortable cotton t-shirt",
        "price": 29.99,
        "current_price": 19.99,
        "price_after_discount": null,
        "price_after_flash_sale": null,
        "sku": "PRD-001",
        "product_type": "simple",
        "in_stock": true,
        "status": "publish",
        "has_discount": true,
        "has_flash_sale": false,
        "discount_type": "percentage",
        "discount_amount": 33,
        "stock_quantity": 100,
        "sold_quantity": 25,
        "image": "https://cdn.example.com/products/1/image.jpg",
        "categories": [
          { "id": 2, "name": "Clothing", "slug": "clothing" }
        ]
      }
    ],
    "current_page": 1,
    "from": 1,
    "to": 15,
    "last_page": 5,
    "per_page": 15,
    "total": 72
  }
}
```

---

## GET /products/{id}

Show single product by ID or slug.

**Auth:** Public

### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer/string | Yes | Product ID or slug |

### Response 200

```json
{
  "success": true,
  "message": "MESSAGE.FETCH_DATA_SUCCESSFULLY",
  "data": {
    "id": 1,
    "name": "T-Shirt",
    "slug": "t-shirt",
    "description": "A comfortable cotton t-shirt",
    "price": 29.99,
    "current_price": 19.99,
    "product_type": "variable",
    "sku": "PRD-001",
    "in_stock": true,
    "status": "publish",
    "has_discount": true,
    "discount_type": "percentage",
    "discount_amount": 33,
    "variants": [
      {
        "id": 10,
        "sku": "VAR-RED-S",
        "price": 29.99,
        "current_price": 19.99,
        "stock_quantity": 50,
        "in_stock": true,
        "attributes": [
          { "id": 1, "name": "Color", "value": "Red" },
          { "id": 5, "name": "Size", "value": "S" }
        ]
      }
    ],
    "categories": [
      { "id": 2, "name": "Clothing", "slug": "clothing" }
    ],
    "brands": [],
    "tags": [],
    "reviews": [],
    "related_products": [],
    "created_at": "2024-01-15T10:00:00Z"
  }
}
```

### Response 404

```json
{
  "success": false,
  "message": "MESSAGE.NOT_FOUND"
}
```

---

## POST /products

Create a new product.

**Auth:** Required (auth:sanctum, email.verified)

**Permission:** `create-product`

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | object | **Yes** | `{ "en": "...", "ar": "..." }` |
| `description` | object | **Yes** | `{ "en": "...", "ar": "..." }` |
| `product_type` | string | **Yes** | `simple` or `variable` |
| `price` | float | sometimes | Required if `product_type=simple` |
| `categories` | array | **Yes** | Array of category IDs |
| `images` | array | **Yes** | Array of uploaded image files |
| `in_stock` | boolean | **Yes** | `1` or `0` |
| `has_discount` | boolean | **Yes** | `true` or `false` or `1` or `0` |
| `has_flash_sale` | boolean | **Yes** | `true` or `false` or `1` or `0` |
| `type_id` | integer | No | Product type ID |
| `quantity` | integer | No | Stock quantity |
| `sku` | string | No | Auto-generated if empty |
| `status` | string | No | One of: `publish`, `draft`, `under_review`, `approved`, `rejected`, `unpublish` |
| `discount_type` | string | No | `percentage` or `fixed_rate` or `free_shipping` (required if has_discount) |
| `discount_amount` | float | No | Required if has_discount |
| `discount_status` | boolean | No | Required if has_discount |
| `start_date` | date | No | Discount start |
| `end_date` | date | No | Discount end (after_or_equal:start_date) |
| `flash_sale_id` | integer | No | Required if has_flash_sale |
| `variants` | array | No | Array of variant objects (required if product_type=variable) |
| `brands` | array | No | Array of brand IDs |
| `banners` | array | No | Array of banner IDs |
| `sliders` | array | No | Array of slider IDs |
| `pieces` | integer | No | Pieces per unit (default: 1) |
| `height/width/length/weight` | string | No | Dimensions |
| `is_fast_shipping_available` | boolean | No | Fast shipping flag |

### Variant Object

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `price` | float | **Yes** | Variant price |
| `quantity` | integer | **Yes** | Variant stock |
| `attribute_values` | array | **Yes** | Array of `attribute_value` IDs |
| `sku` | string | No | Unique SKU |
| `height/width/length/weight` | string | No | Variant dimensions |

### Response 201

```json
{
  "success": true,
  "message": "MESSAGE.CREATE_PRODUCT_SUCCESSFULLY",
  "data": {
    "id": 1,
    "name": "New Product",
    "slug": "new-product",
    "product_type": "simple",
    "price": 49.99,
    "current_price": 49.99,
    "in_stock": true,
    "status": "draft"
  }
}
```

### Validation Errors 422

```json
{
  "name.en": ["The name.en field is required."],
  "categories": ["The categories field is required."],
  "images": ["The images field is required."]
}
```

---

## PUT /products/{id}

Update an existing product.

**Auth:** Required (auth:sanctum, email.verified)

**Permission:** `update-product`

### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Product ID |

### Request Body

Same fields as POST but all are optional (`sometimes`). Only include fields that need updating.

The `name.*` unique validation ignores the current product's own name.

### Response 200

Same shape as POST response with `MESSAGE.UPDATE_PRODUCT_SUCCESSFULLY`.

---

## DELETE /products/{id}

Soft-delete a product.

**Auth:** Required (auth:sanctum, email.verified)

**Permission:** `delete-product`

### Response 200

```json
{
  "success": true,
  "message": "MESSAGE.DELETE_PRODUCT_SUCCESSFULLY"
}
```

### Response 404

```json
{
  "success": false,
  "message": "MESSAGE.NOT_FOUND"
}
```
