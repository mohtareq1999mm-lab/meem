# Product Module — Frontend Integration Guide

## Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/products` | List products (paginated, filterable, searchable) |
| GET | `/products/{id}` | Get product by ID or slug |
| POST | `/products` | Create new product |
| PUT | `/products/{id}` | Update product |
| DELETE | `/products/{id}` | Delete product (soft) |

## Response Structure

### List Response
```json
{
  "success": true,
  "message": "MESSAGE.FETCH_DATA_SUCCESSFULLY",
  "data": {
    "data": [
      {
        "id": 1,
        "name": "Product Name (translated)",
        "slug": "product-name",
        "product_type": "simple|variable",
        "price": 29.99,
        "current_price": 19.99,
        "image": "https://cdn.example.com/thumb.jpg",
        "in_stock": true,
        "status": "publish",
        "categories": [{ "id": 1, "name": "Category", "slug": "category" }]
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

### Single Product Response
```json
{
  "success": true,
  "message": "MESSAGE.FETCH_DATA_SUCCESSFULLY",
  "data": {
    "id": 1,
    "name": "Product Name",
    "slug": "product-name",
    "description": "Full description (translated)",
    "price": 29.99,
    "current_price": 19.99,
    "price_after_discount": null,
    "price_after_flash_sale": null,
    "sku": "PRD-001",
    "product_type": "simple",
    "in_stock": true,
    "status": "publish",
    "stock_quantity": 100,
    "sold_quantity": 25,
    "has_discount": true,
    "discount_type": "percentage",
    "discount_amount": 33,
    "has_flash_sale": false,
    "variants": [],
    "categories": [],
    "tags": [],
    "brands": [],
    "reviews": [],
    "related_products": [],
    "images": ["https://cdn.example.com/full.jpg"],
    "created_at": "2024-01-15T10:00:00Z"
  }
}
```

## States

### Loading
- Show skeleton cards in product grid
- Show skeleton details on product detail page

### Empty
- List returns `{ "data": { "data": [] }, "total": 0 }`
- Show "No products found" with CTA to create first product

### Error
- **401:** Redirect to login
- **403:** Show "You don't have permission"
- **404:** Show "Product not found"
- **422:** Display field-level validation errors on the form
- **500:** Show "Something went wrong" toast

## Key Considerations

### 1. Translatable Fields
- `name` and `description` are JSON objects `{ "en": "...", "ar": "..." }`
- Always send both locales on create; on update you can send just the changed one
- The response returns the translated value based on `app()->getLocale()`

### 2. Product Type
- `simple`: Single product with one price/SKU/stock
- `variable`: Has `variants[]` array with different prices, SKUs, and attribute combinations
- When creating a variable product, include `variants` array. The `price` field is not required (derived from variants)

### 3. Variants
- Each variant has `attribute_values: [id1, id2, ...]` referencing `attribute_values` table
- On update, sending new variants DELETES all old variants and recreates them
- Variant attributes link to `attribute_product` pivot table

### 4. Images
- Images are uploaded as file arrays on create
- On update, send existing image IDs + new files
- Managed by Spatie Media Library

### 5. Discount vs Flash Sale
- `has_discount` + `discount_type`/`discount_amount`: Regular discount
- `has_flash_sale` + `flash_sale_id`: Flash sale discount
- Both can be active simultaneously; `price_after_flash_sale` takes precedence
- Current effective price is in `current_price` field

### 6. Pricing Fields
- `price`: Base price
- `current_price`: Current effective price (accounting for discounts/flash sales)
- `price_after_discount`: Price after regular discount (before flash sale)
- `price_after_flash_sale`: Price after flash sale discount

### 7. Soft Delete
- Products use soft deletes (`deleted_at`)
- Currently no restore endpoint exists

### 8. Search
- Searches across translatable `name`, `description`, `sku`, and variant SKUs
- Uses `LIKE %term%` on JSON fields

### 9. Filters
- `category`: Filter by category slug
- `brand`: Filter by brand slug
- `min_price`/`max_price`: Price range (includes variant prices)
- `tags`: Comma-separated tag slugs (AND logic — product must have ALL)
- `shop_id`: Filter by shop
- `type_id`: Filter by product type
