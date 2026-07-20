# Backend - Order Feature

## Controller - `packages/marvel/src/Http/Controllers/Order/OrderController.php`

Located in the Marvel package namespace `Marvel\Http\Controllers\Order`. Extends `CoreController`, uses `ApiResponse` trait.

### Constructor

```php
$this->middleware('permission:'.Permission::VIEW_ORDERS)->only(['index']);
$this->middleware('permission:'.Permission::VIEW_ORDER)->only(['show']);
```

### index(Request $request)

1. Extract `limit` via `getLimit()` — default 15, max 100, min 1
2. Build query: `Order::query()->with(relations)`
3. Apply 10 conditional filters:
   - `status` — exact match
   - `user_id` — exact match
   - `user_email` — `LIKE %...%`
   - `promotion_id` — exact match
   - `promotion_name` — subquery on `promotion_id WHERE promotion.name LIKE %...%`
   - `product_id` — `whereHas('orderItems.product')`
   - `product_name` — `whereHas('orderItems.product')` with `name LIKE %...%`
   - `flash_sale_name` — 3-level nested `whereHas` (orderItems.product.flash_sales)
   - `shipping_method` — exact match
   - `created_from` / `created_to` — date range
   - `search` — `WHERE name LIKE %...% OR user_email LIKE %...% OR user_phone LIKE %...%`
4. Paginate: `->paginate($limit)->withQueryString()`
5. Return `OrderCollection($orders)`

### show(Request $request, string $param)

1. `Order::query()->with(relations)->findOrFail($param)`
2. Return `OrderResource($order)`

### relations(): array

```php
['user', 'orderItems.product', 'orderItems.productVariant.attributeProducts.attributeValue', 'transactions', 'pickupLocation']
```

### getLimit(Request $request): int

```php
$limit = (int) $request->get('limit', 15);
if ($limit <= 0) return 15;
return min($limit, 100);
```

## API Resources

### OrderCollection

Extends `ResourceCollection`. Wraps `OrderResource`. Returns `data` array + `links` object with pagination metadata.

### OrderResource

| Scope | Fields |
|-------|--------|
| Always | `id`, `order_number`, `status`, `payment_status`, `shipping_method`, `expected_delivery_at`, `customer` (conditionally loaded), `created_at`, `updated_at`, `fast_shipping_fee`, `pickup_location` |
| Only on `orders.show` | `customer_name`, `customer_phone`, `customer_email`, `address`, `notes`, `price`, `shipping_price`, `total_price`, `coupon`, `coupon_discount`, `promotion`, `order_items`, `transactions` |

## Enums

- `Permission::VIEW_ORDERS` — Spatie permission for list
- `Permission::VIEW_ORDER` — Spatie permission for detail

## Model - `packages/marvel/src/Database/Models/Order.php`

Key columns:

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `order_number` | string | Unique order number |
| `tracking_number` | string | Unique tracking number |
| `status` | string | Order status |
| `payment_status` | string | Payment status |
| `user_id` | bigint | FK to users |
| `name` | string | Customer name |
| `user_email` | string | Customer email |
| `user_phone` | string | Customer phone |
| `price` | decimal | Subtotal |
| `shipping_price` | decimal | Shipping cost |
| `total_price` | decimal | Grand total |
| `coupon_discount` | decimal | Coupon discount |
| `promotion_discount` | decimal | Promotion discount |
| `shipping_method` | string | Shipping method |
| `expected_delivery_at` | timestamp | ETA |
| `notes` | text | Order notes |
| `fulfillment_type` | string | `delivery` or `pickup` |
