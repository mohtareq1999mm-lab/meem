# Backend - Order Feature

## Overview

The Order feature is event-driven with a complex lifecycle spanning cart → checkout → payment → fulfillment → delivery → completion. It uses a dual-model system (Marvel legacy + App modern) with a `syncOrderStatusColumn()` bridge.

## Key Files

### 1. Model - `packages/marvel/src/Database/Models/Order.php`

**Table:** `orders`

**Fillable (modern columns):**
`user_id`, `governorate_id`, `name`, `user_phone`, `user_email`, `address`, `notes`, `shipping_method`, `fulfillment_type`, `payment_method`, `payment_gateway`, `pickup_location_id`, `pickup_location_name`, `pickup_location_address`, `pickup_location_phone`, `pickup_location_coordinates`, `price`, `shipping_price`, `total_price`, `fast_shipping_fee`, `coupon`, `coupon_discount`, `promotion_id`, `promotion_code`, `promotion_type`, `promotion_discount`, `status`, `inventory_restored_at`

**Appended:** `order_number` — `'ORD-' . str_pad($this->id, 8, '0', STR_PAD_LEFT)`

**Relationships:**

| Method | Type | Related |
|--------|------|---------|
| `user()` | `BelongsTo` | `User` |
| `orderItems()` | `HasMany` | `OrderProduct` |
| `transactions()` | `HasMany` | `Transaction` |
| `pickupLocation()` | `BelongsTo` | `PickupLocation` |
| `children()` | `HasMany` | `Order` (sub-orders) |

**Scopes:** `forUser()`, `scheduled()`, `fast()`, `delivery()`, `pickup()`

### 2. Model - `packages/marvel/src/Database/Models/OrderProduct.php`

**Table:** `order_products`

**Fillable:** `order_id`, `product_id`, `product_variant_id`, `product_name`, `product_sku`, `attributes`, `product_quantity`, `product_price`, `product_total_price`, `product_discount_price`, `promotion_discount_amount`, `product_flash_sale_price`, `is_gift`, `promotion_id`

**Relationships:** `order()`, `product()`, `productVariant()`, `promotion()`

### 3. Model - `packages/marvel/src/Database/Models/Transaction.php`

**Table:** `transactions`

**Fillable:** `order_id`, `invoice_id`, `payment_method`, `user_id`, `uuid`, `status`, `amount`, `currency`, `gateway_transaction_id`, `gateway_response`, `error_message`, `qr_code_url`, `paid_at`

**Auto UUID generation** on `creating` event.

### 4. Controller (General) - `app/Http/Controllers/Api/General/OrderController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `index()` | `GET /orders` | Returns authenticated user's orders |
| `checkout()` | `POST /checkout` | Creates order from cart |
| `eligiblePromotions()` | `GET /checkout/promotions` | Available promotions |
| `markCodAsPaid()` | `POST /checkout/cod/{id}/mark-paid` | Admin marks COD paid |
| `markCashierPaid()` | `POST /checkout/cashier/{id}/mark-paid` | Admin marks cashier paid |
| `getTransactionQr()` | `GET /checkout/transaction-qr/{uuid}` | Cashier QR code |
| `checkoutCallback()` | `ANY /checkout/callback` | Payment gateway callback |
| `checkoutErrorCallback()` | `ANY /checkout/error-callback` | Gateway error callback |

### 5. Controller (Admin) - `packages/marvel/src/Http/Controllers/Order/OrderController.php`

| Method | Permission | Description |
|--------|-----------|-------------|
| `index()` | `view-orders` | Paginated list (super_admin scope) |
| `show()` | `view-order` | Single order detail |

### 6. Service - `app/Services/General/OrderService.php`

| Method | Description |
|--------|-------------|
| `changeOrderStatus($invoice_id, $status, $user)` | Status transition with validation |
| `syncStatus($orderId)` | Syncs legacy order_status → modern status |
| `markCodAsPaid($orderId, $user)` | Mark COD paid, update transaction |

### 7. Service - `app/Services/Checkout/OrderCreationService.php`

| Method | Description |
|--------|-------------|
| `createOrder($request, $user)` | Creates order in transaction |
| `createOrderItems($order, $cart, $promotion)` | Snapshots product prices |
| `finalizeOrder($order, $cart)` | Updates inventory, clears cart, dispatches events |

### 8. Enums

**OrderStatus:** `pending`, `processing`, `completed`, `cancelled`, `refunded`, `failed`, `at_local_facility`, `out_for_delivery`, `ready_for_pickup`

**PaymentStatus:** `pending`, `processing`, `success`, `failed`, `reversal`, `refunded`, `cash_on_delivery`, `cash`, `wallet`, `awaiting_for_approval`

**PaymentGatewayType:** 14 types (Stripe, PayPal, MyFatoorah, Razorpay, etc.)

**FulfillmentType:** `delivery`, `pickup`

**ShippingMethod:** `SCHEDULED`, `FAST`

### 9. Permissions

| Permission | Value |
|------------|-------|
| `VIEW_ORDERS` | `view-orders` |
| `VIEW_ORDER` | `view-order` |
| `CREATE_ORDER` | `create-order` |
| `UPDATE_ORDER_STATUS` | `update-order-status` |
| `VIEW_REFUNDS` | `view-refunds` |
| `CREATE_REFUND` | `create-refund` |

### 10. Events (11)

| Event | Dispatched When | Key Listeners |
|-------|----------------|---------------|
| `OrderCreated` | Checkout completes | SendNewOrderNotification |
| `OrderReceived` | Admin receives order | SendOrderReceivedNotification |
| `OrderProcessed` | Order processing starts | (none — stock deducted synchronously) |
| `OrderDelivered` | Order marked delivered | SendOrderDeliveredNotification |
| `OrderCancelled` | Order cancelled | SendOrderCancelledNotification (x2) |
| `OrderStatusChanged` | Any status change | SendOrderStatusChangedNotification (x2) |
| `PaymentSucceeded` | Payment verified | (inventory release) |
| `PaymentFailed` | Payment failed | (transaction update) |

### 11. GraphQL

**Queries:**
- `orders(tracking_number, orderBy, customer_id, shop_id)` — Paginated
- `order(id, tracking_number)` — Single

**Mutations:**
- `createOrder(input: CreateOrderInput!)` — `OrderMutator@store`
- `updateOrder(input: UpdateOrderInput!)` — `OrderMutator@update`
- `deleteOrder(id: ID!)` — `@delete` with super_admin can
- `createOrderPayment(input)` — `OrderMutator@createOrderPayment`
- `generateOrderExportUrl(input)` — Export URL
- `generateInvoiceDownloadUrl(input)` — Invoice URL

## Known Issues

1. **Dual Model System:** Marvel legacy columns (`tracking_number`, `order_status`, `payment_status`, `amount`, `total`, `paid_total`) vs modern App columns (`status`, `price`, `total_price`). `syncOrderStatusColumn()` bridges the gap.
2. **Commented Routes:** Standard `apiResource('orders')` routes are commented out in Routes.php.
3. **No Base Migration Found:** `create_orders_table` migration missing (may be squashed).
4. **inventory_restored_at** guard column prevents double-restoration on cancellation.
