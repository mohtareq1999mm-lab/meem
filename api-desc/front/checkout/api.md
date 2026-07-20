# API Reference — Checkout Module

Base URL: `/api/v1/general`

---

### GET /api/v1/general/checkout/promotions

List eligible promotions and gift products.

**Auth:** `auth:sanctum`

**Response 200:**
```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": {
    "promotions": [{ "id": 1, "code": "BUY2GET1", "type": "buy_x_get_y", "gift_product": {...} }],
    "gift_products": [{ "id": 20, "name": "Free Item", "thumbnail": "..." }]
  }
}
```

---

### POST /api/v1/general/checkout

Place order. Supports online, cod, pay_at_cashier.

**Auth:** `auth:sanctum`

**Request:**
```json
{
  "name": "John Doe",
  "user_phone": "+1-555-0123",
  "user_email": "john@example.com",
  "address": { "street": "123 Main St" },
  "payment_method": "online",
  "gateway": "myfatoorah",
  "fulfillment_type": "delivery",
  "governorate_id": 1
}
```

**Validation:** name (required), user_phone (required), user_email (required, email), address (required, array), payment_method (in:online,cod,pay_at_cashier), fulfillment_type (in:delivery,pickup), governorate_id (requiredIf delivery), pickup_location_id (requiredIf pickup), selected_promotion_id (nullable, exists), selected_gift_product_id (nullable, exists)

**Response 200 (online):** `{ "data": { "url": "https://gateway.com/pay/123" } }`
**Response 200 (COD):** `{ "data": { "order_id": 1 } }`
**Response 200 (cashier):** `{ "data": { "order_id": 1, "transaction_uuid": "abc", "qr_code": "data:..." } }`
**Response 422 (COD+pickup):** `{ "message": "COD not available for pickup" }`

---

### POST /api/v1/general/checkout/cod/{orderId}/mark-paid

**Auth:** `auth:sanctum` + `permission:update-order-status`
**Response 200:** `{ "message": "Payment successful", "success": true }`

---

### POST /api/v1/general/checkout/cashier/{orderId}/mark-paid

**Auth:** `auth:sanctum` + `permission:update-order-status`
**Response 200:** Same structure.

---

### GET /api/v1/general/checkout/transaction-qr/{uuid}

**Auth:** `auth:sanctum`
**Response 200:** Raw SVG (`Content-Type: image/svg+xml`)
**Response 403:** Other user's transaction
**Response 404:** Not found

---

### ANY /api/v1/general/checkout/callback

**Auth:** None
**Query:** `?paymentId=GTX123`
**Response:** Redirect to `/payment/success` or `/payment/failed`

---

### ANY /api/v1/general/checkout/error-callback

**Auth:** None
**Query:** `?paymentId=GTX123`
**Response:** Redirect to `/payment/failed`

---

## Business Rules
- Requires active cart with SCHEDULED items
- Prices recalculated in real-time at checkout
- Inventory finalized immediately for COD/cashier, on callback for online
- Order stores immutable pricing snapshots
- Coupon quota consumed only on successful payment (never returned)
- COD not available for pickup
- Pay-at-cashier requires pickup
- Mobile clients get JSON instead of redirect (type=mobile)
