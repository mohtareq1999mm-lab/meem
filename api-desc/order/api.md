# API Documentation - Order Feature

All endpoints require `auth:sanctum` and are under prefix `/api/v1`.

## 1. Order List

**GET** `/api/v1/orders`

### Query Parameters

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| `limit` | int | No | Per page (default 15, max 100) |
| `page` | int | No | Page number |
| `status` | string | No | Filter by order status |
| `user_id` | int | No | Filter by user ID |
| `user_email` | string | No | Partial match on user email |
| `promotion_id` | int | No | Filter by promotion ID |
| `promotion_name` | string | No | Search promotion by name |
| `product_id` | int | No | Orders containing this product |
| `product_name` | string | No | Orders containing product with name like |
| `flash_sale_name` | string | No | Orders with flash sale products |
| `shipping_method` | string | No | Filter by shipping method |
| `created_from` | date | No | Start date (Y-m-d) |
| `created_to` | date | No | End date (Y-m-d) |
| `search` | string | No | Search name, email, phone |

### Response

```json
{
    "data": [
        {
            "id": 1,
            "order_number": "ORD-20260720-0001",
            "status": "completed",
            "payment_status": "paid",
            "shipping_method": "standard",
            "expected_delivery_at": "2026-07-25T00:00:00+00:00",
            "customer": {
                "id": 5,
                "name": "Ahmed",
                "email": "ahmed@example.com",
                "phone": "+201234567890"
            },
            "created_at": "2026-07-20T10:30:00+00:00",
            "updated_at": "2026-07-20T11:00:00+00:00",
            "fast_shipping_fee": 0.00,
            "pickup_location": null
        }
    ],
    "links": {
        "current_page": 1,
        "from": 1,
        "to": 15,
        "last_page": 5,
        "path": "https://example.com/api/v1/orders",
        "per_page": 15,
        "total": 75,
        "next_page_url": "https://example.com/api/v1/orders?page=2",
        "prev_page_url": null
    }
}
```

## 2. Order Detail

**GET** `/api/v1/orders/{id}`

`{id}` can be the primary ID or tracking number.

### Response

```json
{
    "data": {
        "id": 1,
        "order_number": "ORD-20260720-0001",
        "status": "completed",
        "payment_status": "paid",
        "shipping_method": "standard",
        "expected_delivery_at": "2026-07-25T00:00:00+00:00",
        "customer": {
            "id": 5,
            "name": "Ahmed",
            "email": "ahmed@example.com",
            "phone": "+201234567890"
        },
        "customer_name": "Ahmed",
        "customer_phone": "+201234567890",
        "customer_email": "ahmed@example.com",
        "address": {"street": "..."},
        "notes": null,
        "price": 200.00,
        "shipping_price": 30.00,
        "total_price": 230.00,
        "coupon": null,
        "coupon_discount": 0.00,
        "promotion": null,
        "order_items": [
            {
                "id": 1,
                "product_id": 10,
                "product_name": "Widget",
                "quantity": 2,
                "unit_price": 100.00,
                "subtotal": 200.00
            }
        ],
        "transactions": [
            {
                "id": 1,
                "amount": 230.00,
                "gateway": "CASH_ON_DELIVERY",
                "status": "completed"
            }
        ],
        "fast_shipping_fee": 0.00,
        "pickup_location": null,
        "created_at": "2026-07-20T10:30:00+00:00",
        "updated_at": "2026-07-20T11:00:00+00:00"
    }
}
```

## Business Rules

1. **Permission gating:** `index` requires `VIEW_ORDERS`, `show` requires `VIEW_ORDER`
2. **Dual resolution:** `show` accepts ID or tracking number
3. **Conditional fields:** `customer_name`, `customer_phone`, `customer_email`, financial fields, `order_items`, `transactions` only present in `show` response (merged conditionally via `routeIs('orders.show')`)
4. **Pagination:** Default 15, max 100
5. **No ordering:** List does not apply explicit `orderBy`
