# API Documentation - Product Feature

## Endpoints

---

### 1. List Products (Public - Strategy-based)

**GET** `/api/v1/general/products`

**Purpose:** Retrieve products based on display strategy. Supports multiple listing modes, advanced filtering, and full-text search.

#### Authentication

| Aspect | Detail |
|--------|--------|
| Required | No |

#### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `type` | `string` | No | Strategy: `index`, `best_product_sales`, `brands_product`, `new_arrivals`, `all_product_discounts`, `product_discount_today_or_low_qty`, `flash_sales_product`, `flash_sales_end_today`, `product_for_parent_category`, `flash_sales_end_week` |
| `search` | `string` | No | Full-text search (name, desc, sku, categories) |
| `limit` | `integer` | No | Per page (max 100, default 30) |
| `order` | `asc|desc` | No | Sort by ID |
| `order_price` | `asc|desc` | No | Sort by current price |
| `category` | `string|array` | No | Filter by category slug (recursive) |
| `brand` | `string|array` | No | Filter by brand name/slug |
| `tag` | `string|array` | No | Filter by tag slug (AND logic) |
| `promotion` | `string|array` | No | Filter by promotion slug |
| `flash_sale` | `string|array` | No | Filter by flash sale |
| `banner` | `string|array` | No | Filter by banner slug/title |
| `slider` | `string|array` | No | Filter by slider slug |
| `minPrice` | `float` | No | Minimum price |
| `maxPrice` | `float` | No | Maximum price |
| `rating` | `float` | No | Minimum avg rating |
| `productsId` | `string` | No | Comma-separated product IDs |
| `categoriesId` | `string` | No | Comma-separated category IDs |
| `brandsId` | `string` | No | Comma-separated brand IDs |

#### Success Response (200)

```json
{
    "data": [
        {
            "id": 1,
            "name": "Wireless Headphones",
            "slug": "wireless-headphones",
            "price": 99.99,
            "current_price": 79.99,
            "in_stock": true,
            "quantity": 50,
            "has_discount": true,
            "discount_active": true,
            "flash_sale_active": false,
            "is_fast_shipping_available": true,
            "ratings": 4.5,
            "image": { "id": 1, "thumbnail": "https://...", "original": "https://..." }
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 30,
        "total": 150
    }
}
```

---

### 2. Get Product by Slug (Public)

**GET** `/api/v1/general/products/{slug}`

**Purpose:** Retrieve a single product with full details, reviews, related products, and dynamic filters.

#### Authentication

| Aspect | Detail |
|--------|--------|
| Required | No |

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `limit` | `integer` | Number of related products to include |

#### Success Response (200)

```json
{
    "data": {
        "id": 1,
        "name": "Wireless Headphones",
        "slug": "wireless-headphones",
        "description": "Premium wireless headphones with noise cancellation.",
        "price": 99.99,
        "current_price": 79.99,
        "price_after_discount": 79.99,
        "price_after_flash_sale": null,
        "discount_type": "percentage",
        "discount_amount": 20,
        "start_date": "2026-07-01",
        "end_date": "2026-07-31",
        "product_type": "simple",
        "sku": "PRD-001",
        "in_stock": true,
        "images": [{ "id": 1, "thumbnail": "...", "original": "..." }],
        "categories": [{ "id": 1, "name": "Electronics", "slug": "electronics" }],
        "reviews": [{ "id": 1, "rating": 5, "comment": "Great product!" }],
        "related_products": [{ "id": 2, "name": "Earbuds", "slug": "earbuds", "current_price": 49.99 }],
        "filters": { "brands": [...], "categories": [...], "attributes": [...] }
    }
}
```

#### Error Responses

| Status | Condition |
|--------|-----------|
| 404 | Product not found |

---

### 3. List Products (Admin)

**GET** `/api/v1/products`

**Purpose:** Retrieve paginated list of all products with advanced filtering for admin management.

#### Authentication

| Aspect | Detail |
|--------|--------|
| Required | Yes |
| Guard | `sanctum` |
| Permission | `view-products` |

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | `integer` | Page number |
| `search` | `string` | Search by name/sku |
| `sort` | `string` | Sort field |
| `orderBy` | `string` | Order direction |
| `limit` | `integer` | Per page |
| `status` | `string` | Filter by status |
| `category` | `integer` | Filter by category ID |

#### Success Response (200)

```json
{
    "data": [
        {
            "id": 1,
            "name": "Wireless Headphones",
            "slug": "wireless-headphones",
            "price": 99.99,
            "current_price": 79.99,
            "sku": "PRD-001",
            "status": "publish",
            "product_type": "simple",
            "in_stock": true,
            "quantity": 50
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 10,
        "per_page": 15,
        "total": 150
    }
}
```

---

### 4. Create Product (Admin)

**POST** `/api/v1/products`

**Purpose:** Create a new product with variants, images, categories, and discount/flash sale configuration.

#### Authentication

| Aspect | Detail |
|--------|--------|
| Required | Yes |
| Permission | `create-product` |

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | `array` | Yes | Translatable name (`{ en, ar }`) |
| `description` | `array` | Yes | Translatable description (max 10000) |
| `product_type` | `string` | Yes | `simple` or `variable` |
| `categories` | `array` | Yes | Array of category IDs |
| `images` | `array` | Yes | Array of image files (jpeg,png,jpg, max 2MB) |
| `in_stock` | `boolean` | Yes | Stock availability |
| `has_discount` | `boolean` | Yes | Has discount |
| `has_flash_sale` | `boolean` | Yes | Has flash sale |
| `price` | `numeric` | Sometimes | Required if product_type=simple |
| `type_id` | `integer` | No | Associated type/collection ID |
| `quantity` | `integer` | No | Stock quantity |
| `pieces` | `integer` | No | Pieces per unit (min 1) |
| `status` | `string` | No | ProductStatus enum value |
| `discount_type` | `string` | No | `percentage` or `fixed` |
| `discount_amount` | `numeric` | No | Discount value |
| `start_date` | `date` | No | Discount start |
| `end_date` | `date` | No | Discount end (after start_date) |
| `variants` | `array` | No | Array of variant objects |

#### Success Response (201)

```json
{
    "data": {
        "id": 1,
        "name": "Wireless Headphones",
        "slug": "wireless-headphones",
        "price": 99.99,
        "current_price": 79.99,
        "product_type": "simple",
        "status": "publish"
    }
}
```

---

### 5. Update Product (Admin)

**PUT** `/api/v1/products/{id}`

**Purpose:** Update an existing product (partial updates supported). Recategorization, re-pricing, and variant changes are fully synced.

#### Authentication

| Aspect | Detail |
|--------|--------|
| Required | Yes |
| Permission | `update-product` |

#### Success Response (200)

Returns updated product resource.

---

### 6. Delete Product (Admin)

**DELETE** `/api/v1/products/{id}`

**Purpose:** Soft-delete a single product.

#### Authentication

| Aspect | Detail |
|--------|--------|
| Required | Yes |
| Permission | `delete-product` |

#### Success Response (200)

```json
{
    "message": "Product deleted successfully"
}
```

---

### 7. Bulk Delete Products (Admin)

**POST** `/api/v1/products/bulk-delete`

**Purpose:** Soft-delete multiple products at once.

#### Authentication

| Aspect | Detail |
|--------|--------|
| Required | Yes |
| Permission | `delete-product` |

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `ids` | `array` | Yes | Array of product IDs (min 1, distinct) |

---

### 8. Best Selling Products (Public)

**GET** `/api/v1/best-selling-products`

**Purpose:** Retrieve top-selling products ordered by completed order volume.

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `limit` | `integer` | Number of products (default 10) |
| `range` | `string` | Date range for calculation |
| `type_slug` | `string` | Filter by type slug |
| `shop_id` | `integer` | Filter by shop |

---

### 9. Popular Products (Public)

**GET** `/api/v1/popular-products`

**Purpose:** Retrieve popular products by total order count.

---

### 10. Calculate Rental Price (Public)

**GET** `/api/v1/products/calculate-rental-price`

**Purpose:** Calculate rental pricing based on dates, quantity, persons, locations, deposits, and features.

#### Query Parameters

| Parameter | Type | Required |
|-----------|------|----------|
| `product_id` | `integer` | Yes |
| `from` | `date` | Yes |
| `to` | `date` | Yes |
| `quantity` | `integer` | Yes |
| `persons` | `integer` | No |
| `dropoff_location_id` | `integer` | No |
| `pickup_location_id` | `integer` | No |

---

### 11. GraphQL

**Query Products:**
```graphql
query {
    products(search: "headphones", orderBy: [{ column: "price", order: ASC }]) {
        data {
            id
            name
            price
            current_price
            categories { id name }
        }
    }
}
```

**Create Product:**
```graphql
mutation {
    createProduct(input: {
        name: "Wireless Headphones"
        product_type: simple
        price: 99.99
        categories: [1, 2]
    }) {
        id
        name
        slug
    }
}
```

---

## Resource Structure

### ProductMiniResource (Public List)

| Field | Type | Description |
|-------|------|-------------|
| `id` | `integer` | Primary key |
| `name` | `string` | Translated name |
| `slug` | `string` | URL slug |
| `price` | `float` | Base price |
| `current_price` | `float` | Effective price |
| `in_stock` | `boolean` | Stock flag |
| `discount_active` | `boolean` | Discount active |
| `ratings` | `float` | Average rating |
| `image` | `object` | Thumbnail + original URLs |

### ProductResource (Public Detail)

| Field | Type | Description |
|-------|------|-------------|
| `id` | `integer` | Primary key |
| `name` | `string` | Translated name |
| `slug` | `string` | URL slug |
| `description` | `string` | Translated description |
| `price` | `float` | Base price |
| `current_price` | `float` | Final effective price |
| `sku` | `string` | Stock keeping unit |
| `product_type` | `string` | simple/variable |
| `in_stock` | `boolean` | Stock flag |
| `categories` | `array` | Category list |
| `images` | `array` | Image gallery |
| `reviews` | `array` | Customer reviews |
| `variants` | `array` | Product variants |
| `related_products` | `array` | Related products |
| `filters` | `object` | Dynamic filters |

## Business Rules

1. **Pricing Hierarchy:** Flash Sale price > Product Discount price > Base price
2. **SKU Generation:** Auto-generated as `PRD-{id}` (zero-padded to 3 digits) on creation
3. **Strategy Pattern:** Public listing uses a Strategy pattern (ProductEngine) with 10+ strategies for different display modes
4. **Soft Delete:** Products are soft-deleted; variants and pivot records handled via model events
5. **Search:** Laravel Scout (Meilisearch) for full-text; translatable LIKE for admin
6. **Variants:** Variable products create ProductVariant records with attribute values
7. **Reviews:** Authenticated users can create/update reviews with image attachments
8. **Fast Shipping:** Global scope filters products by `is_fast_shipping_available` when channel context is fast-shipping
9. **Channel Filter:** Home channel excludes fast-shipping products
10. **Import/Export:** Background jobs with progress tracking for large CSV operations
