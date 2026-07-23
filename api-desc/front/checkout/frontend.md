# Checkout Module — Frontend Integration Guide

---

### 1. GET /api/v1/general/checkout/promotions — Eligible Promotions

**Authentication:** Required (`auth:sanctum`)

**Response 200:**
```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": { "promotions": [...], "gift_products": [...] }
}
```

**Response 400:** `{ "message": "Cart not found", "success": false }`

---

### 2. POST /api/v1/general/checkout — Place Order

**Authentication:** Required (`auth:sanctum`)

**Request Body:**
```json
{
  "name": "John Doe",
  "user_phone": "+1-555-0123",
  "user_email": "john@example.com",
  "address": { "street": "123 Main St" },
  "notes": "Leave at door",
  "payment_method": "online",
  "gateway": "myfatoorah",
  "fulfillment_type": "delivery",
  "governorate_id": 1,
  "selected_promotion_id": null,
  "selected_gift_product_id": null,
  "type": "web"
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| name | required, string, max:255 |
| user_phone | required, string, max:255 |
| user_email | required, email, max:255 |
| address | required, array |
| notes | nullable, string |
| payment_method | nullable, in:online,cod,pay_at_cashier |
| gateway | nullable, string, max:50 |
| fulfillment_type | nullable, in:delivery,pickup |
| governorate_id | requiredIf fulfillment=delivery, exists:governorates,id |
| pickup_location_id | requiredIf fulfillment=pickup, exists:pickup_locations,id |
| selected_promotion_id | nullable, exists:promotions,id |
| selected_gift_product_id | nullable, exists:products,id |

**Response 200 (online):** `{ "data": { "url": "https://gateway.com/pay/123" } }`
**Response 200 (COD):** `{ "data": { "order_id": 1 } }`
**Response 200 (cashier):** `{ "data": { "order_id": 1, "transaction_uuid": "abc", "qr_code": "data:..." } }`
**Response 400 (minimum order):** `{ "success": false, "message": "Minimum order amount is 100", "errors": {} }`
**Response 422 (COD+pickup):** `{ "message": "COD not available for pickup", "success": false }`

---

### 3. POST /api/v1/general/checkout/cod/{orderId}/mark-paid — Mark COD Paid

**Auth:** `auth:sanctum` + `permission:update-order-status`
**Response 200:** `{ "message": "Payment successful", "success": true }`

---

### 4. POST /api/v1/general/checkout/cashier/{orderId}/mark-paid — Mark Cashier Paid

**Auth:** `auth:sanctum` + `permission:update-order-status`
**Response 200:** Same structure.

---

### 5. GET /api/v1/general/checkout/transaction-qr/{uuid} — Get QR Code

**Auth:** `auth:sanctum`
**Response 200:** Raw SVG (`Content-Type: image/svg+xml`)
**Response 403:** Other user's transaction
**Response 404:** Not found

---

### 6. ANY /api/v1/general/checkout/callback — Payment Callback

**Auth:** None (public)
**Query:** `?paymentId=GTX123`
**Behavior:** Redirects to `/payment/success` or `/payment/failed` on frontend.

---

### 7. ANY /api/v1/general/checkout/error-callback — Error Callback

**Auth:** None (public)
**Query:** `?paymentId=GTX123`
**Behavior:** Redirects to `/payment/failed`.

---

## Frontend Usage

### State Handling

| State | Behavior |
|-------|----------|
| **Form loading** | Skeleton form |
| **Promotions loading** | Spinner on promotion section |
| **No promotions** | Hide section |
| **Submitting** | Button spinner, fields disabled |
| **COD success** | Order confirmation page |
| **Online success** | Redirect/open payment URL |
| **Cashier success** | Show QR code for scanning |
| **Min order not met** | Show banner "Minimum order amount is X" |
| **Validation error** | Inline errors |
| **Callback success** | Success page with order ID |
| **Callback failed** | Failure page with error |
