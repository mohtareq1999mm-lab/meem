# API Documentation - Order Feature

The Order feature has **two separate endpoint groups** with different response shapes:

| Group | Base path | Controller | Response resources |
|-------|-----------|-----------|--------------------|
| **Customer** (frontend app) | `/api/v1/general/orders` | `App\Http\Controllers\Api\General\OrderController` | `App\Http\Resources\Order\*` |
| **Admin** (dashboard) | `/api/v1/orders` | `Marvel\Http\Controllers\Order\OrderController` | `Marvel\Http\Resources\Order\*` |

All endpoints require `auth:sanctum`. Customer endpoints additionally use `throttle:authenticated`; admin endpoints use `throttle:admin` plus Spatie permissions.

Standard success envelope (both groups, via `Marvel\Traits\ApiResponse`):

```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": {}
}
```

---

## CUSTOMER ENDPOINTS

## 1. My Orders List

**GET** `/api/v1/general/orders`

### Authentication

- `auth:sanctum`
- `throttle:authenticated`

### Query Parameters

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| `limit` | int | No | Per page (default 15, max 100, min 1) |
| `page` | int | No | Page number |
| `status` | string | No | Filter by order status |

The list is always scoped to the authenticated user (`Order::forUser($userId)`). It cannot be used to read another user's orders.

### Response

```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": {
        "data": [
            {
                "id": 1,
                "order_number": "ORD-20260720-0001",
                "status": "completed",
                "subtotal": 200.0,
                "discount": 20.0,
                "coupon": "SAVE10",
                "coupon_discount": 20.0,
                "coupon_discount_type": "percentage",
                "promotion_discount": 0.0,
                "total": 210.0,
                "converted_total": 210.0,
                "currency": "EGP",
                "base_currency": "EGP",
                "catalog_currency": "EGP",
                "exchange_rate": null,
                "promotion": null,
                "fulfillment_type": "delivery",
                "payment_method": "online",
                "shipping_price": 30.0,
                "fast_shipping_fee": 0.0,
                "created_at": "2026-07-20T10:30:00+00:00",
                "order_items": [
                    {
                        "id": 1,
                        "quantity": 2,
                        "unit_price": 100.0,
                        "total_price": 200.0,
                        "converted_unit_price": 100.0,
                        "converted_total_price": 200.0,
                        "promotion_discount_amount": 0.0,
                        "is_gift": false,
                        "promotion_id": null,
                        "product": {
                            "id": 10,
                            "name": "Widget",
                            "slug": "widget",
                            "price": 100.0,
                            "has_variants": false,
                            "current_price": 100.0,
                            "currency": "EGP",
                            "quantity": 50,
                            "in_stock": true,
                            "discount_active": false,
                            "flash_sale_active": false,
                            "is_fast_shipping_available": false,
                            "ratings": 4.5,
                            "tags": [],
                            "image": {
                                "thumbnail": "http://example.com/storage/products/1/w1.jpg",
                                "original": []
                            }
                        },
                        "variant": null
                    }
                ],
                "payment_gateway": "myfatoorah",
                "order_has_invoice": true,
                "invoice_id": "550e8400-e29b-41d4-a716-446655440000"
            }
        ],
        "links": {
            "current_page": 1,
            "from": 1,
            "to": 1,
            "last_page": 1,
            "path": "http://example.com/api/v1/general/orders",
            "per_page": 15,
            "total": 1,
            "next_page_url": null,
            "prev_page_url": null,
            "last_page_url": "http://example.com/api/v1/general/orders?page=1",
            "first_page_url": "http://example.com/api/v1/general/orders?page=1"
        }
    }
}
```

### Field notes

- `discount` = `coupon_discount + promotion_discount` (computed in the resource).
- `order_has_invoice` (bool) and `invoice_id` (latest invoice **uuid** or `null`) indicate whether the order has an invoice.
- `invoice_summary` is **only present** when the `invoices` relation is loaded. It is NOT emitted by the list endpoint (only `latestInvoice` is loaded).
- `pickup_location` is only emitted when `fulfillment_type === "pickup"`.
- `product` is a `ProductMiniResource` when the relation is loaded, otherwise `{ id, name, sku }`.
- `variant` is an `OrderProductVariantResource` when loaded, otherwise `{ id, attributes }`, otherwise `null`.
- Monetary values are rounded to 2 decimals (`roundMoney`).

## 2. Customer Order Invoice View

**GET** `/api/v1/general/orders/invoice/{uuid}`

### Authentication

- `auth:sanctum`
- `throttle:authenticated`

### Authorization

- Only the **owner** of the invoice's order can view it.
- Non-owner: **403** `You are not authorized to perform this action`.
- Not found: **404**.

### Response

```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": {
        "uuid": "550e8400-e29b-41d4-a716-446655440000",
        "invoice_number": "INV-2026-000001",
        "status": "ready",
        "subtotal": 200.0,
        "shipping_price": 30.0,
        "total_discount": 20.0,
        "total": 210.0,
        "currency": "EGP",
        "payment_method": "online",
        "payment_gateway": "myfatoorah",
        "generated_at": "2026-07-20T10:35:00+00:00",
        "pdf_generated_at": "2026-07-20T10:40:00+00:00",
        "verification_url": "http://example.com/api/v1/general/invoices/verify/550e8400-e29b-41d4-a716-446655440000",
        "snapshot": {
            "order": { "id": 1, "order_number": "ORD-20260720-0001" },
            "customer": { "name": "Ahmed" },
            "pricing_breakdown": {
                "subtotal": 200.0,
                "total_discount": 20.0,
                "total": 210.0
            }
        }
    }
}
```

This uses `App\Http\Resources\Invoice\CustomerInvoiceResource`. `snapshot` is only present when the invoice has stored data. `download_url` in this resource points to a route that does NOT exist — use `GET /api/v1/invoices/{uuid}/download` for downloads (see the Invoice documentation).

---

## ADMIN ENDPOINTS

## 3. Admin Order List

**GET** `/api/v1/orders`

### Authentication

- `auth:sanctum`
- `throttle:admin`
- Permission: **`view-orders`** (Spatie, `Permission::VIEW_ORDERS`)

### Query Parameters

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| `limit` | int | No | Per page (default 15, max 100) |
| `page` | int | No | Page number |
| `status` | string | No | Filter by order status |
| `user_id` | int | No | Filter by user ID |
| `user_email` | string | No | Partial match on user email |
| `promotion_id` | int | No | Filter by promotion ID |
| `promotion_name` | string | No | Orders whose promotion name matches (`LIKE`) |
| `product_id` | int | No | Orders containing this product |
| `product_name` | string | No | Orders containing product with name like |
| `flash_sale_name` | string | No | Orders with flash sale products (title LIKE) |
| `shipping_method` | string | No | Filter by shipping method |
| `created_from` | date | No | Start date (Y-m-d) |
| `created_to` | date | No | End date (Y-m-d) |
| `search` | string | No | Search `name`, `user_email`, `user_phone` (`LIKE`) |

### Response

The list uses `Marvel\Http\Resources\Order\OrderCollection`. List items do **NOT** include `order_items`, `transactions`, or financial fields (those are `orders.show`-only):

```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": {
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
                "fast_shipping_fee": 0.0,
                "pickup_location": {
                    "id": 3,
                    "store_name": "Cairo Store",
                    "address": "5 Tahrir St",
                    "phone": "+201111111111",
                    "email": "store@example.com",
                    "working_hours": "10:00-22:00",
                    "latitude": "30.0444",
                    "longitude": "31.2357",
                    "status": true
                }
            }
        ],
        "links": {
            "current_page": 1,
            "from": 1,
            "to": 1,
            "last_page": 1,
            "path": "http://example.com/api/v1/orders",
            "per_page": 15,
            "total": 1,
            "next_page_url": null,
            "prev_page_url": null
        }
    }
}
```

### Field notes

- `customer` is emitted only when the `user` relation is loaded (it is). `phone` maps from `user->phone_number`.
- `pickup_location` is emitted only when `fulfillment_type === "pickup"`.
- No explicit `orderBy` is applied to the list query.

## 4. Admin Order Detail

**GET** `/api/v1/orders/{id}`

`{id}` can be the primary ID **or** tracking number.

### Authentication

- `auth:sanctum`
- `throttle:admin`
- Permission: **`view-order`** (Spatie, `Permission::VIEW_ORDER`)

### Response

The detail uses `Marvel\Http\Resources\Order\OrderResource`. Full detail fields are only merged on the `orders.show` route:

```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
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
        "address": "5 Tahrir St, Cairo",
        "notes": null,
        "price": 200.0,
        "shipping_price": 30.0,
        "total_price": 230.0,
        "coupon": null,
        "coupon_discount": 0.0,
        "promotion": null,
        "order_items": [
            {
                "id": 1,
                "product_id": 10,
                "product_variant_id": null,
                "product_name": "Widget",
                "product_sku": "WDG-100",
                "quantity": 2,
                "unit_price": 100.0,
                "total_price": 200.0,
                "discount_price": 0.0,
                "flash_sale_price": null,
                "promotion_discount_amount": 0.0,
                "is_gift": false,
                "promotion_id": null,
                "attributes": null,
                "product": {
                    "id": 10,
                    "name": "Widget",
                    "slug": "widget",
                    "image": "http://example.com/storage/products/1/w1.jpg"
                },
                "variant": null
            }
        ],
        "transactions": [
            {
                "id": 1,
                "uuid": "abc12345-1111-1111-1111-111111111111",
                "invoice_id": "MF-20260720-001",
                "payment_method": "myfatoorah",
                "status": "paid",
                "amount": 230.0,
                "created_at": "2026-07-20T10:35:00+00:00"
            }
        ],
        "created_at": "2026-07-20T10:30:00+00:00",
        "updated_at": "2026-07-20T11:00:00+00:00",
        "fast_shipping_fee": 0.0,
        "pickup_location": null
    }
}
```

### Admin OrderItemResource fields

| Field | Type | Notes |
|-------|------|-------|
| `id` | int | |
| `product_id` | int | |
| `product_variant_id` | int \| null | |
| `product_name` | string | |
| `product_sku` | string | |
| `quantity` | int | from `product_quantity` |
| `unit_price` | float | rounded, from `product_price` |
| `total_price` | float | rounded, from `product_total_price` |
| `discount_price` | float | rounded, from `product_discount_price` |
| `flash_sale_price` | float \| null | rounded, from `product_flash_sale_price` |
| `promotion_discount_amount` | float | rounded |
| `is_gift` | bool | |
| `promotion_id` | int \| null | |
| `attributes` | mixed | raw attributes |
| `product` | object | `{id, name, slug, image}` when relation loaded |
| `variant` | object \| null | `{id, sku, price, in_stock, attributes}` when loaded |

### Admin OrderTransactionResource fields

`id`, `uuid`, `invoice_id`, `payment_method`, `status`, `amount`, `created_at` (ISO8601).

---

## Business Rules

1. **Permission gating (admin):** `index` requires `view-orders`, `show` requires `view-order`.
2. **Dual resolution (admin):** `show` accepts ID or tracking number.
3. **Conditional fields (admin):** `customer_name`, `customer_phone`, `customer_email`, `address`, `notes`, `price`, `shipping_price`, `total_price`, `coupon`, `coupon_discount`, `promotion`, `order_items`, `transactions` are only present on the `orders.show` route (merged via `routeIs('orders.show')`).
4. **Customer scoping:** the customer list (`/api/v1/general/orders`) is always filtered to `forUser($request->user()->id)`.
5. **Pagination:** default 15, max 100 (admin: `getLimit`, customer: `getLimit` in `OrderService`).
6. **No ordering (admin):** the admin list does not apply an explicit `orderBy`.
7. **Money rounding:** all monetary values are rounded to 2 decimals in resources.
8. **Invoice indicator (customer):** `order_has_invoice` / `invoice_id` tell the frontend whether an invoice exists for the order; `invoice_id` is the invoice **uuid**.