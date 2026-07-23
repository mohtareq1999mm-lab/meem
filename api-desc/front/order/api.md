# API Documentation - Order Feature

## Endpoints

---

### 1. List My Orders (Customer)

**GET** `/api/v1/general/orders`

**Purpose:** Retrieve authenticated user's order history.

#### Authentication

| Aspect | Detail |
|--------|--------|
| Required | Yes |
| Guard | `sanctum` |

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | `integer` | Page number |
| `limit` | `integer` | Items per page (default: 15, max: 100) |
| `status` | `string` | Filter by order status (`pending`, `processing`, `completed`, `cancelled`, `delivered`, `refunded`, `failed`, `at_local_facility`, `out_for_delivery`, `ready_for_pickup`) |
| `search` | `string` | Search by order ID or status |

#### Success Response (200)

```json
{
    "data": [
        {
            "id": 1,
            "order_number": "ORD-00000001",
            "status": "pending",
            "total_price": 150.00,
            "payment_method": "cod",
            "shipping_method": "SCHEDULED",
            "created_at": "2026-07-20T10:00:00Z",
            "items_count": 3
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 75
    }
}
```

---

### 2. Checkout (Create Order)

**POST** `/api/v1/general/checkout`

**Purpose:** Create a new order from the user's cart. Supports COD, online payment, and cashier payment.

#### Authentication

| Aspect | Detail |
|--------|--------|
| Required | Yes |
| Guard | `sanctum` |

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | `string` | Yes | Customer name |
| `user_phone` | `string` | Yes | Customer phone |
| `user_email` | `string` | Yes | Customer email |
| `address` | `array` | Yes | Delivery address |
| `payment_method` | `string` | No | `online`, `cod`, `pay_at_cashier` |
| `fulfillment_type` | `string` | No | `delivery`, `pickup` |
| `governorate_id` | `integer` | Required if delivery | Governorate ID |
| `pickup_location_id` | `integer` | Required if pickup | Pickup location ID |
| `selected_promotion_id` | `integer` | No | Applied promotion ID |
| `selected_gift_product_id` | `integer` | No | Gift product ID |
| `gateway` | `string` | No | Payment gateway name |
| `notes` | `string` | No | Order notes |

#### Success Response (201)

```json
{
    "success": true,
    "message": "Order created successfully",
    "data": {
        "order_id": 1,
        "total": 150.00
    }
}
```

---

### 3. Mark COD as Paid (Admin)

**POST** `/api/v1/general/checkout/cod/{orderId}/mark-paid`

**Purpose:** Mark a COD order as paid by the admin/staff.

#### Authentication

| Aspect | Detail |
|--------|--------|
| Required | Yes |
| Permission | `update-order-status` |

#### Success Response (200)

```json
{
    "success": true,
    "message": "Order marked as paid successfully"
}
```

---

### 4. Payment Callback (Public)

**ANY** `/api/v1/general/checkout/callback`

**Purpose:** Payment gateway callback endpoint. Verifies payment with gateway, updates transaction and order status.

| Aspect | Detail |
|--------|--------|
| Required | No |

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `paymentId` | `string` | Gateway payment ID |

---

### 5. List Orders (Admin)

**GET** `/api/v1/orders`

**Purpose:** Retrieve paginated list of all orders (super admin scope).

#### Authentication

| Aspect | Detail |
|--------|--------|
| Required | Yes |
| Role | `super_admin` |
| Permission | `view-orders` |

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | `integer` | Page number |
| `search` | `string` | Search |
| `status` | `string` | Filter by status |
| `date_range` | `string` | Date range filter |

#### Success Response (200)

Standard paginated response with full order resources.

---

### 6. Order Statuses (Enum)

```php
OrderStatus::PENDING           = 'order-pending'
OrderStatus::PROCESSING        = 'order-processing'
OrderStatus::COMPLETED         = 'order-completed'
OrderStatus::CANCELLED         = 'order-cancelled'
OrderStatus::REFUNDED          = 'order-refunded'
OrderStatus::FAILED            = 'order-failed'
OrderStatus::AT_LOCAL_FACILITY = 'order-at-local-facility'
OrderStatus::OUT_FOR_DELIVERY  = 'order-out-for-delivery'
OrderStatus::READY_FOR_PICKUP  = 'order-ready-for-pickup'
```

### Payment Statuses (Enum)

```php
PaymentStatus::PENDING               = 'payment-pending'
PaymentStatus::PROCESSING            = 'payment-processing'
PaymentStatus::SUCCESS               = 'payment-success'
PaymentStatus::FAILED                = 'payment-failed'
PaymentStatus::REVERSAL              = 'payment-reversal'
PaymentStatus::REFUNDED              = 'payment-refunded'
PaymentStatus::CASH_ON_DELIVERY      = 'payment-cash-on-delivery'
PaymentStatus::CASH                  = 'payment-cash'
PaymentStatus::WALLET                = 'payment-wallet'
PaymentStatus::AWAITING_FOR_APPROVAL = 'payment-awaiting-for-approval'
```

---

## Business Rules

1. **Status Transition:** `pending → processing → completed → delivered` (strict chain). Cancelled is terminal
2. **Inventory Lock:** Items locked during checkout, restored on cancellation via `RestoreInventoryOnRefund`
3. **Payment Verification:** Online payments verified via gateway callback; COD/Cashier marked by staff
4. **Coupon + Promotion:** Both can be applied; promotion discounts and coupon discounts stack
5. **Price Snapshots:** Order items preserve product prices at time of order (immutable)
6. **Order Number:** Auto-generated as `ORD-{id}` (zero-padded to 8 digits)
7. **Tracking Number:** Format `YYYYMMDD` + 6 random digits
8. **Export:** Background export with signed URL for download
9. **Invoice Download:** Token-based secure download with expiry
10. **Events:** 11 events dispatched throughout lifecycle with queued notifications
