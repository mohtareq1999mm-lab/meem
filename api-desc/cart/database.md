# Database — Cart Module

## Table: `carts`

| Column | Type | Default | Constraints | Notes |
|--------|------|---------|-------------|-------|
| id | bigint unsigned | AUTO_INCREMENT | PK | |
| user_id | bigint unsigned | NULL | FK → users.id (nullOnDelete) | null when user deleted |
| coupon | varchar(255) | NULL | | Applied coupon code |
| total_price | decimal(?,?) | NULL | | Sum of item total_prices |
| status | varchar(255) | 'active' | | active, checked_out, expired |
| reserved_at | datetime | NULL | | Last reservation timestamp |
| expires_at | datetime | NULL | | TTL = reserved_at + 3 days |
| created_at | timestamp | NULL | | |
| updated_at | timestamp | NULL | | |

### Indexes
- Primary: `id`
- Foreign: `user_id` → `users.id`

### Foreign Keys
- `user_id` → `users.id` ON DELETE SET NULL (nullOnDelete)

### Status Values
| Status | Meaning |
|--------|---------|
| `active` | Cart in use, items reserved |
| `checked_out` | Cart finalized, stock deducted |
| `expired` | TTL exceeded, stock released |

---

## Table: `cart_items`

| Column | Type | Default | Constraints | Notes |
|--------|------|---------|-------------|-------|
| id | bigint unsigned | AUTO_INCREMENT | PK | |
| cart_id | bigint unsigned | | FK → carts.id | Parent cart |
| product_id | bigint unsigned | NULL | FK → products.id (nullOnDelete) | null when product deleted |
| product_variant_id | bigint unsigned | NULL | FK → product_variants.id | nullable for simple products |
| quantity | int | | NOT NULL | Desired quantity |
| price | decimal | | | Snapshotted unit price at reservation |
| total_price | decimal | | | `ROUND(price * quantity, 2)` |
| attributes | json | NULL | | Variant attributes / custom values |
| reserved_quantity | int | | | Quantity held in stock reservation |
| discount_amount | decimal | 0 | | Promotion discount on this item |
| shipping_method | varchar(255) | 'SCHEDULED' | | SCHEDULED or FAST |
| is_gift | tinyint(1) | 0 | | Free gift item from promotion |
| promotion_id | bigint unsigned | NULL | FK → promotions.id | Link to promotion |
| created_at | timestamp | NULL | | |
| updated_at | timestamp | NULL | | |

### Indexes
- Primary: `id`
- Foreign: `cart_id` → `carts.id`
- Foreign: `product_id` → `products.id`
- Foreign: `product_variant_id` → `product_variants.id`
- Foreign: `promotion_id` → `promotions.id`

### Foreign Keys
- `cart_id` → `carts.id` ON DELETE CASCADE
- `product_id` → `products.id` ON DELETE SET NULL
- `product_variant_id` → `product_variants.id` ON DELETE SET NULL
- `promotion_id` → `promotions.id` ON DELETE SET NULL

---

## Fillable Mass Assignment

### Cart Model
```php
protected $fillable = [
    'user_id', 'coupon', 'total_price', 'status', 'reserved_at', 'expires_at',
];
```

### CartItem Model
```php
protected $fillable = [
    'cart_id', 'product_id', 'product_variant_id', 'quantity', 'price',
    'total_price', 'attributes', 'reserved_quantity', 'discount_amount',
    'shipping_method', 'is_gift', 'promotion_id',
];
```

---

## Casts

### Cart Model
```php
protected $casts = [
    'reserved_at' => 'datetime',
    'expires_at' => 'datetime',
];
```

### CartItem Model
```php
protected $casts = [
    'price' => 'float',
    'total_price' => 'float',
    'attributes' => 'array',
    'is_gift' => 'boolean',
    'shipping_method' => 'string',
];
```

---

## Related Tables

| Table | Relation | Column |
|-------|----------|--------|
| `users` | BelongsTo (Cart) | `carts.user_id` |
| `products` | BelongsTo (CartItem, withTrashed) | `cart_items.product_id` |
| `product_variants` | BelongsTo (CartItem) | `cart_items.product_variant_id` |
| `promotions` | BelongsTo (CartItem) | `cart_items.promotion_id` |
| `coupons` | Referenced by code | `carts.coupon` |

---

## Migration Files

| File | Table |
|------|-------|
| `packages/marvel/database/migrations/..._create_carts_table.php` | `carts` |
| `packages/marvel/database/migrations/..._create_cart_items_table.php` | `cart_items` |
| `packages/marvel/database/migrations/2026_07_17_000001_fix_cart_foreign_key_cascades.php` | FK fix (nullOnDelete) |

---

## Query Patterns

### N+1 Prevention — Eager Loading
```php
// CartController index
Cart::where('user_id', $userId)
    ->with(['items.product', 'items.productVariant.attributeProducts.attributeValue.attribute'])
    ->paginate($perPage);

// CartController show
$cart->load(['items.product', 'items.productVariant.attributeProducts.attributeValue.attribute']);
```

### Row Locking — Inventory Concurrency
```php
// Lock cart row
Cart::where('user_id', $userId)->lockForUpdate()->first();

// Lock inventory row (product or variant)
ProductVariant::whereKey($variant->id)->lockForUpdate()->firstOrFail();
// or
Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

// Lock existing cart item
CartItem::where('cart_id', $cart->id)
    ->where('product_id', $productId)
    ->where('is_gift', false)
    ->lockForUpdate()
    ->first();
```

### Total Price Recalculation
```php
// In repository (SQL rounding)
$cart->update(['total_price' => $cart->items()->sum('total_price')]);

// In inventory service (PHP rounding)
$totalPrice = round($price * $desiredQuantity, 2);

// In resource
'subtotal' => round((float) $items->sum('total_price'), 2),
```

### Promotion Revalidation
```php
$cart->items()
    ->whereNotNull('promotion_id')
    ->orWhere('discount_amount', '>', 0)
    ->update([
        'promotion_id' => null,
        'discount_amount' => 0,
        'total_price' => DB::raw('ROUND(price * quantity, 2)'),
    ]);
```

### Cart Expiration Query
```php
Cart::where('status', 'active')
    ->whereNotNull('expires_at')
    ->where('expires_at', '<=', now())
    ->chunk(100, function ($carts) { ... });
```

### Coupon Clear on Last Item Delete
```php
if (CartItem::where('cart_id', $cartId)->count() === 0) {
    Cart::where('id', $cartId)->update(['coupon' => null]);
}
```
