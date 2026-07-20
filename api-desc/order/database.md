# Database - Order Feature

## Tables Queried

| Table | Usage | Relations |
|-------|-------|-----------|
| `orders` | Primary table | `user`, `pickup_location` |
| `users` | Eager loaded | `order.user` |
| `order_items` | Eager loaded (via `orderItems`) | `order.orderItems` |
| `products` | Eager loaded (via `orderItems.product`) | `orderItems.product` |
| `product_variants` | Eager loaded (via `orderItems.productVariant`) | `orderItems.productVariant` |
| `attribute_products` | Eager loaded (via `productVariant.attributeProducts`) | `productVariant.attributeProducts` |
| `attribute_values` | Eager loaded (via `attributeProducts.attributeValue`) | `attributeProducts.attributeValue` |
| `transactions` | Eager loaded (via `transactions`) | `order.transactions` |
| `pickup_locations` | Eager loaded (via `pickupLocation`) | `order.pickupLocation` |

## Query Pattern

### List

```sql
SELECT * FROM orders
WHERE status = ?              -- if status filter
  AND user_id = ?             -- if user_id filter
  AND user_email LIKE ?       -- if user_email filter
  AND promotion_id IN (       -- if promotion_name filter
    SELECT code FROM promotions WHERE name LIKE ?
  )
  AND EXISTS (                -- if product_id filter
    SELECT 1 FROM order_items WHERE order_id = orders.id AND product_id = ?
  )
  AND EXISTS (                -- if flash_sale_name filter
    SELECT 1 FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    JOIN flash_sales fs ON ... WHERE fs.title LIKE ?
  )
  AND shipping_method = ?     -- if shipping_method filter
  AND created_at >= ?         -- if created_from filter
  AND created_at <= ?         -- if created_to filter
  AND (name LIKE ? OR user_email LIKE ? OR user_phone LIKE ?) -- if search filter
ORDER BY ?                    -- no explicit orderBy (depends on scope)
LIMIT ? OFFSET ?
```

### Show

```sql
SELECT * FROM orders WHERE id = ? OR tracking_number = ? LIMIT 1
```

## Eager Loaded Relations

5 top-level relations, with nested sub-relations for products and attributes — up to 9 tables joined.
