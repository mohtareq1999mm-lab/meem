# Checkout Module — Frontend Flow Guide

## Overview — The Full User Journey

```
User
  │
  ├── 1. Browse products → add to cart
  ├── 2. View cart
  ├── 3. Start checkout
  │       ├── Choose fulfillment: Delivery OR Pickup
  │       └── Choose payment: Online / COD / Pay at Cashier
  ├── 4. Place order → POST /checkout
  ├── 5. Handle result
  │       ├── Online       → redirect to payment gateway
  │       ├── COD          → show success screen
  │       └── Pay at Cashier → show QR code
  └── 6. Track order → GET /orders
```

---

## Step 1 — Cart Management

All cart endpoints are at `/api/v1/cart` (NOT under `v1/general`).

### Endpoints

| Action | Method | Endpoint | Description |
|--------|--------|----------|-------------|
| View cart | GET | `/api/v1/cart` | List user's carts with items |
| Add item | POST | `/api/v1/cart` | Add single product to cart |
| Bulk add | POST | `/api/v1/cart/bulk-items` | Add multiple items at once |
| Update item | PUT | `/api/v1/cart/update-item` | Change quantity or variant |
| Remove item | DELETE | `/api/v1/cart/delete-item/{itemId}` | Remove one item |
| Clear cart | DELETE | `/api/v1/cart/delete-items` | Remove all items |

### Cart Item Fields (for POST/PUT)

```json
{
  "product_id": 1,
  "product_variant_id": null,
  "quantity": 2,
  "shipping_method": "scheduled"
}
```

`shipping_method`: `"scheduled"` or `"fast"`

### Cart Response Fields

```json
{
  "id": 1,
  "items": [
    {
      "id": 1,
      "product_id": 1,
      "product_variant_id": null,
      "quantity": 2,
      "unit_price": 100.00,
      "total_price": 200.00,
      "product": { "id": 1, "name": "...", "slug": "...", "image": "..." },
      "product_variant": null
    }
  ],
  "total_price": 200.00,
  "coupon": null,
  "applied_promotion": null
}
```

### Coupon Application

Apply a coupon via `POST /api/v1/general/coupons/apply` (auth required).

The coupon discount is stored on the cart and used during checkout.

### State Handling

| State | Behavior |
|-------|----------|
| **Loading** | Skeleton product list |
| **Empty cart** | "Your cart is empty" + link to shop |
| **Adding item** | Button spinner on product card |
| **Updating qty** | Debounced input, spinner on total |
| **Removing item** | Confirm dialog, then remove |
| **Coupon error** | Inline error under coupon input |

---

## Step 2 — Load Checkout Prerequisites

Before showing the checkout form, fetch these resources in parallel:

```
┌─────────────────────────────────────────────────────┐
│  Parallel fetches                                   │
│                                                     │
│  GET /api/v1/general/checkout/promotions            │
│  GET /api/v1/general/governorates (if delivery)     │
│  GET /api/v1/general/pickup-locations (if pickup)   │
│  GET /api/v1/cart (already have from cart page)     │
└─────────────────────────────────────────────────────┘
```

### GET /checkout/promotions

**Auth:** `auth:sanctum`

Returns eligible promotions and gift products for the user's current cart.

```json
{
  "success": true,
  "data": {
    "promotions": [
      { "id": 1, "code": "BUY2GET1", "type": "buy_x_get_y", "gift_product": {...} }
    ],
    "gift_products": [
      { "id": 20, "name": "Free Item", "thumbnail": "..." }
    ]
  }
}
```

If no cart exists: `400 { "message": "Cart not found" }`

### GET /api/v1/general/governorates

**Auth:** None (public)

See `api-desc/front/governorate/frontend.md` for full details.

```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Cairo", "country_id": 1, "status": true, "is_fast_shipping_enabled": true }
  ]
}
```

### GET /api/v1/general/pickup-locations

**Auth:** None (public)

See `api-desc/front/pickLocation/frontend.md` for full details.

```json
{
  "success": true,
  "data": [
    { "id": 1, "store_name": "Downtown Branch", "address": "...", ... }
  ]
}
```

---

## Step 3 — Choose Fulfillment & Payment

The checkout has two independent dimensions:

```
            ┌──────────────┬──────────────────┬──────────────────┐
            │              │    Delivery       │     Pickup       │
├───────────┼──────────────┼──────────────────┼──────────────────┤
│  Online   │     ✅       │     ✅           │     ✅           │
│  COD      │     ✅       │     ✅           │     ❌           │
│  Cashier  │     ✅       │     ❌           │     ✅           │
└───────────┴──────────────┴──────────────────┴──────────────────┘
```

### Rules

| Fulfillment | Available Payments | Requirements |
|-------------|-------------------|--------------|
| **Delivery** | Online, COD | `governorate_id`, `address`, `name`, `phone` |
| **Pickup** | Online, Pay at Cashier | `pickup_location_id` (no address needed) |

### What NOT to Show

| Scenario | Hide / Disable |
|----------|---------------|
| Pickup selected | Hide COD option entirely |
| Delivery selected | Hide "Pay at Cashier" option entirely |
| Fast shipping items | (fast shipping has its own separate flow) |

---

## Step 4 — Submit Order

### POST /api/v1/general/checkout

**Auth:** `auth:sanctum`

### Request Body

```json
{
  "name": "John Doe",
  "user_phone": "+201234567890",
  "user_email": "john@example.com",
  "address": { "street": "12 Main St", "building": "5", "apartment": "3" },
  "notes": "Leave at door",
  "payment_method": "online",
  "gateway": "myfatoorah",
  "fulfillment_type": "delivery",
  "governorate_id": 1,
  "pickup_location_id": null,
  "selected_promotion_id": null,
  "selected_gift_product_id": null,
  "type": "web"
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| name | required, string, max:255 |
| user_phone | required, string, max:255 |
| user_email | required, email, max:255 |
| address | required, array |
| notes | nullable, string |
| payment_method | nullable, in:online,cod,pay_at_cashier |
| gateway | required for online, string, max:50 |
| fulfillment_type | nullable, in:delivery,pickup |
| governorate_id | requiredIf fulfillment=delivery, exists:governorates,id |
| pickup_location_id | requiredIf fulfillment=pickup, exists:pickup_locations,id |
| selected_promotion_id | nullable, exists:promotions,id |
| selected_gift_product_id | nullable, exists:products,id |
| type | nullable, in:web,mobile |

### Responses by Payment Method

**Online Payment — 200:**
```json
{
  "success": true,
  "data": {
    "url": "https://sandbox.myfatoorah.com/pay/INV-123"
  }
}
```
→ Frontend redirects user to this URL.

**COD — 200:**
```json
{
  "success": true,
  "data": {
    "order_id": 42
  }
}
```
→ Frontend shows success page with order number.

**Pay at Cashier — 200:**
```json
{
  "success": true,
  "data": {
    "order_id": 42,
    "transaction_uuid": "abc-123-def",
    "qr_code": "data:image/svg+xml;base64,..."
  }
}
```
→ Frontend shows QR code immediately.

**Error — 422 (COD + Pickup):**
```json
{
  "success": false,
  "message": "COD not available for pickup"
}
```

---

## Step 5 — Handle Post-Checkout by Payment Type

### Online Payment Flow

```
POST /checkout → 200 { url }
     │
     ▼
Redirect user to payment gateway URL
     │
     ▼
User completes payment on gateway page
     │
     ├── Success → Gateway redirects to /callback?paymentId=GTX123
     │              │
     │              ▼
     │         Backend verifies payment
     │         ┌─────────────────────────────────────┐
     │         │ 1. Find transaction                 │
     │         │ 2. Gateway::verifyPayment(paymentId) │
     │         │ 3. Validate amount/currency          │
     │         │ 4. If mismatch → cancel order        │
     │         │ 5. If valid → complete order         │
     │         └─────────────────────────────────────┘
     │              │
     │              ▼
     │         Redirect: /payment/success?order_id=42
     │
     └── Failure → Gateway redirects to /error-callback?paymentId=GTX123
                   │
                   ▼
              Redirect: /payment/failed
```

**Frontend Responsibilities:**
- Show loading spinner during redirect
- Handle the callback URLs in your frontend router (`/payment/success`, `/payment/failed`)
- On success page: show order number, order details, "View Order" button
- On failure page: show error message, "Retry Payment" button (retry = start checkout again)

### COD Flow

```
POST /checkout → 200 { order_id }
     │
     ▼
Show success screen: "Order Placed! Pay when delivered."
     │
     ▼
Wait delivery → pay driver → status updates via admin
```

**Frontend Responsibilities:**
- Show order confirmation page with order number
- No redirect needed
- Order says "Payment pending" until driver collects

### Pay at Cashier (QR) Flow

```
POST /checkout → 200 { qr_code, transaction_uuid, order_id }
     │
     ▼
Display QR code on screen
     │
     ▼
Customer visits store
     │
     ▼
Cashier scans QR → marks paid
     │
     ▼
Poll order status OR wait for websocket
```

**Frontend Responsibilities:**
- Display QR code image (data URI from `qr_code` field)
- Save QR locally so customer can reopen it later
- Show "Get QR" button on orders screen while payment is pending
- Refresh periodically or use polling

---

## Step 6 — QR Code Details

### Initial QR (from checkout response)
The `qr_code` field in the checkout response is a base64 data URI:
```
data:image/svg+xml;base64,PHN2ZyB4bWxucz0i...
```
Display it directly as an `<img src>`.

### GET /api/v1/general/checkout/transaction-qr/{uuid}

Use when user needs to view QR again from orders screen.

**Auth:** `auth:sanctum`

**Response:** Raw SVG (`Content-Type: image/svg+xml`)

**Errors:**
- 403 — Not your transaction
- 404 — Transaction not found

### QR Screen Content

```
┌──────────────────────────┐
│         QR Code          │
│      [large image]       │
│                          │
│  Order: ORD-00000042     │
│  Branch: Downtown Store  │
│  Amount: EGP 250.00      │
│  Generated: 12:30 PM     │
│                          │
│  Status: Pending Payment │
│                          │
│  [Refresh QR] [Download] │
│  [Back to Orders]        │
└──────────────────────────┘
```

---

## Step 7 — Track Orders

### GET /api/v1/general/orders

**Auth:** `auth:sanctum`

Returns paginated list of the user's orders.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 42,
      "order_number": "ORD-00000042",
      "status": "order-pending",
      "payment_status": "payment-pending",
      "payment_method": "online",
      "fulfillment_type": "delivery",
      "total_price": 250.00,
      "created_at": "2026-07-23T12:00:00+00:00",
      "items": [...]
    }
  ],
  "meta": { "current_page": 1, "last_page": 5, "total": 42 }
}
```

### Order Status Values (for progress timeline)

```
order-pending
     ↓
order-processing
     ↓
order-at-local-facility OR order-ready-for-pickup
     ↓
order-out-for-delivery
     ↓
order-completed
```

Also: `order-cancelled`, `order-refunded`, `order-failed`

### Payment Status Values (computed)

| Status | Meaning |
|--------|---------|
| `payment-pending` | Not paid yet |
| `payment-processing` | Payment in progress |
| `payment-success` | Paid successfully |
| `payment-failed` | Payment failed |
| `payment-cash-on-delivery` | COD (will be collected) |
| `payment-cash` | Paid at cashier |

### Orders Screen

```
┌──────────────────────────────────────────────┐
│  Orders                                      │
│                                              │
│  ┌────────────────────────────────────────┐  │
│  │ ORD-00000042         Jul 23, 2026      │  │
│  │ Status: Pending                        │  │
│  │ Payment: Pending (Pay at Cashier)      │  │
│  │ Total: EGP 250.00                      │  │
│  │                              [View QR] │  │
│  └────────────────────────────────────────┘  │
│  ┌────────────────────────────────────────┐  │
│  │ ORD-00000041         Jul 22, 2026      │  │
│  │ Status: Delivered                      │  │
│  │ Payment: Paid (Online)                 │  │
│  │ Total: EGP 150.00                      │  │
│  │                              [Details] │  │
│  └────────────────────────────────────────┘  │
└──────────────────────────────────────────────┘
```

Show the "View QR" button only when:
- `payment_method === "pay_at_cashier"`
- `payment_status === "payment-pending"`

---

## Step 8 — Error & Edge Case Handling

### Online Payment

| Scenario | Frontend Action |
|----------|----------------|
| Checkout returns URL | Redirect to URL |
| User returns from gateway success | Show success page |
| User returns from gateway failure | Show failure page with retry |
| Callback redirect to /payment/success | Parse `order_id` from query, show details |
| Callback redirect to /payment/failed | Show error message |
| Payment gateway timeout | Show "Payment timed out, try again" |

### COD

| Scenario | Frontend Action |
|----------|----------------|
| Checkout returns order_id | Show success screen |
| Order never marked paid | Shows "pending" status forever |
| Order cancelled by admin | Status changes to cancelled |

### Pay at Cashier

| Scenario | Frontend Action |
|----------|----------------|
| Checkout returns QR | Display QR immediately |
| User closes QR page | Save QR locally (localStorage) |
| User reopens from orders | Call GET /transaction-qr/{uuid} |
| QR expired/not found | Show "QR not found, contact store" |
| Payment completed | Poll updates status to "paid" |

### General Errors

| Status | Meaning | Frontend Action |
|--------|---------|----------------|
| 400 | Cart not found | Redirect to cart page |
| 422 | Validation error | Show inline field errors |
| 500 | Server error | Show "Something went wrong, try again" |
| 401 | Unauthenticated | Redirect to login |

---

## UX Recommendations

- **Disable checkout button** while request is processing
- **Prevent double-submit** — track a `submitting` state
- **Cache the latest QR** in localStorage (key: `last_qr_{orderId}`)
- **Refresh order status** every 30 seconds on order details screen
- **Handle expired sessions** — if checkout returns 401, save form data to sessionStorage and redirect to login
- **Mobile vs Web** — set `type: "mobile"` for mobile apps (callback returns JSON instead of redirect)
- **Governorates dropdown** — cache locally for the session, they rarely change
- **Pickup locations dropdown** — cache for 5 minutes

---

## Complete API Reference Table

| # | Method | Endpoint | Auth | Purpose |
|---|--------|----------|------|---------|
| 1 | GET | `/api/v1/general/checkout/promotions` | sanctum | Eligible promotions |
| 2 | GET | `/api/v1/general/governorates` | Public | Governorates dropdown |
| 3 | GET | `/api/v1/general/pickup-locations` | Public | Pickup locations dropdown |
| 4 | POST | `/api/v1/general/checkout` | sanctum | Place order |
| 5 | ANY | `/api/v1/general/checkout/callback` | Public | Gateway success callback |
| 6 | ANY | `/api/v1/general/checkout/error-callback` | Public | Gateway failure callback |
| 7 | GET | `/api/v1/general/checkout/transaction-qr/{uuid}` | sanctum | Get QR code image |
| 8 | GET | `/api/v1/general/orders` | sanctum | List user's orders |

---

## Related Documentation

| Module | File |
|--------|------|
| Governorates | `api-desc/front/governorate/frontend.md` |
| Pickup Locations | `api-desc/front/pickLocation/frontend.md` |
| Cart API | `packages/marvel/src/Http/Controllers/CartController.php` |
| Backend flow | `api-desc/front/checkout/flow.md` |
| Backend details | `api-desc/front/checkout/backend.md` |
| API reference | `api-desc/front/checkout/api.md` |
