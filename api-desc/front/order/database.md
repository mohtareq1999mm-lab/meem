# Database - Order Feature

## Tables

### `orders` Table

**Migrations (modifications only — no base create found):**
- `2026_07_08_000001_add_fulfillment_and_payment_to_orders.php`
- `2026_07_08_141643_add_not_null_constraints_to_orders_and_transactions.php`
- `2026_07_11_000001_add_governorate_id_to_orders.php`
- `2026_07_11_000004_add_pickup_location_snapshot_to_orders.php`
- `2026_07_14_103540_add_inventory_restored_at_to_orders.php`

| Column | Type | Default |
|--------|------|---------|
| `id` | `bigint unsigned` | PK |
| `user_id` | `bigint unsigned` | FK → users |
| `governorate_id` | `bigint unsigned` | NULLABLE |
| `name` | `varchar(255)` | |
| `user_phone` | `varchar(255)` | |
| `user_email` | `varchar(255)` | |
| `address` | `json` | |
| `notes` | `text` | NULLABLE |
| `shipping_method` | `varchar(50)` | `SCHEDULED` |
| `fulfillment_type` | `varchar(50)` | `delivery` |
| `payment_method` | `varchar(50)` | |
| `pickup_location_id` | `bigint unsigned` | NULLABLE |
| `price` | `decimal(10,2)` | (subtotal) |
| `shipping_price` | `decimal(10,2)` | 0 |
| `total_price` | `decimal(10,2)` | |
| `fast_shipping_fee` | `decimal(10,2)` | 0 |
| `status` | `varchar(50)` | `pending` |
| `inventory_restored_at` | `timestamp` | NULLABLE |

**Indexes:** `user_id`, `status`, `created_at`

### `order_products` Table

| Column | Type |
|--------|------|
| `id` | `bigint unsigned` PK |
| `order_id` | `bigint unsigned` FK |
| `product_id` | `bigint unsigned` FK |
| `product_variant_id` | `bigint unsigned` NULLABLE |
| `product_name` | `varchar(255)` |
| `product_sku` | `varchar(255)` |
| `attributes` | `json` |
| `product_quantity` | `int` |
| `product_price` | `decimal(10,2)` |
| `product_total_price` | `decimal(10,2)` |
| `product_discount_price` | `decimal(10,2)` NULLABLE |
| `promotion_discount_amount` | `decimal(10,2)` NULLABLE |
| `product_flash_sale_price` | `decimal(10,2)` NULLABLE |
| `is_gift` | `boolean` |

### `transactions` Table

| Column | Type |
|--------|------|
| `id` | `bigint unsigned` PK |
| `order_id` | `bigint unsigned` FK |
| `uuid` | `varchar(255)` UNIQUE |
| `status` | `varchar(50)` |
| `amount` | `decimal(10,2)` |
| `payment_method` | `varchar(50)` |
| `gateway_transaction_id` | `varchar(255)` NULLABLE |
| `qr_code_url` | `text` NULLABLE |
| `paid_at` | `timestamp` NULLABLE |

Auto-generates UUID on creation.

### `refunds` Table

Tracks refund requests with amount, status, and reason.

## Query Patterns

| Use Case | Query |
|----------|-------|
| My orders | `Order::forUser($userId)->orderBy('created_at', 'desc')->paginate()` |
| Admin list | `Order::with('user','orderItems')->filter($request)->paginate()` |
| Order detail | `Order::with('orderItems.product','transactions')->findOrFail($id)` |
| Status update | `Order::where('id', $id)->update(['status' => $newStatus])` |
| Revenue aggregation | `Order::where('status', 'completed')->sum('total_price')` |
