# Checkout Module — Backend Architecture

## Endpoints

| Method | URL | Auth | Permission | Purpose |
|--------|-----|------|------------|---------|
| GET | `/api/v1/general/checkout/promotions` | auth:sanctum | — | List eligible promotions |
| POST | `/api/v1/general/checkout` | auth:sanctum | — | Place order |
| POST | `/api/v1/general/checkout/cod/{orderId}/mark-paid` | auth:sanctum | update-order-status | Mark COD paid |
| POST | `/api/v1/general/checkout/cashier/{orderId}/mark-paid` | auth:sanctum | update-order-status | Mark cashier paid |
| GET | `/api/v1/general/checkout/transaction-qr/{uuid}` | auth:sanctum | — | Get QR SVG |
| ANY | `/api/v1/general/checkout/callback` | Public | — | Payment callback |
| ANY | `/api/v1/general/checkout/error-callback` | Public | — | Error callback |

## Middleware

| Endpoint | auth:sanctum | permission | Named route |
|----------|:---:|:---:|:---:|
| GET /checkout/promotions | ✓ | — | — |
| POST /checkout | ✓ | — | — |
| POST /cod/{id}/mark-paid | ✓ | update-order-status | — |
| POST /cashier/{id}/mark-paid | ✓ | update-order-status | — |
| GET /transaction-qr/{uuid} | ✓ | — | — |
| ANY /callback | — | — | api.checkout.callback |
| ANY /error-callback | — | — | api.checkout.errorCallback |

## Request Flows

### Eligible Promotions
```
GET /checkout/promotions
  → OrderService::eligiblePromotionsForUser()
    → getCartUser() → Cart with SCHEDULED items
    → PromotionService::eligiblePromotionsPayload($cart)
  → Response: 200 { promotions, gift_products }
```

### Checkout (Online)
```
POST /checkout { payment_method: "online" }
  → OrderCreateRequest validation
  → ensureCartReservation (lock + sync)
  → COD+pickup check
  → OrderService::addItemsInOrder()
    → DB::transaction
      → Cart::lockForUpdate (SCHEDULED items)
      → refreshCartItemPrices (real-time)
      → Validate coupon (lock row)
      → Calculate totals (promotion + coupon + shipping)
      → Enforce minimumOrderAmount (against subtotal, pre-discount)
      → OrderCreationService::createOrder (snapshot pricing)
      → createOrderItems, finalizeOrder
      → finalizeItemsByShippingMethod(SCHEDULED)
    → PaymentCheckoutHandler::handleOnlinePayment
      → Gateway::createInvoice
      → Transaction::create(status=pending)
    → Response: 200 { url }
```

### Mark COD/Cashier Paid
```
POST /cod/{id}/mark-paid
  → Order::findOrFail
  → markCodAsPaid (or markCashierPaid)
    → Lock pending transaction
    → Update: transaction=paid, order=completed
    → recordCouponUsage
    → event(PaymentSucceeded)
  → Response: 200
```

### Payment Callback
```
ANY /callback?paymentId=X
  → Find transaction
  → Gateway::verifyPayment
  → Amount/currency mismatch check
  → Success: finalize inventory, order=completed, redirect /success
  → Failure: cancel order, release cart, redirect /failed
```

## Key Classes

| Class | Responsibility |
|-------|----------------|
| `OrderController` | HTTP entry points (7 methods) |
| `OrderService` | Order orchestration, pricing, status management |
| `OrderCreationService` | Order + order items persistence |
| `PaymentCheckoutHandler` | Online/COD/cashier payment routing |
| `PaymentGatewayFactory` | Gateway resolution by name |
| `CashierQrService` | QR code generation (base64, SVG) |

## Model: Order

Key columns: user_id, name, user_phone, user_email, address (json), fulfillment_type, payment_method, governorate_id, pickup_location_id, price, shipping_price, total_price, coupon, coupon_discount, promotion_id, promotion_discount, status.

Status machine: pending → processing → completed → delivered. Cancelled from any state.

## Model: Transaction

Key columns: order_id, user_id, uuid (unique), invoice_id, gateway_transaction_id, payment_method, status (pending/paid/failed), amount, currency, paid_at.

## Pricing Order

1. Refresh cart prices (real-time via ProductPricingService)
2. Apply promotion (PromotionService::applySelectedPromotion)
3. Apply coupon (CouponCalculator)
4. Calculate shipping (governorate-based, free shipping thresholds)
5. Free shipping from coupon (if discount_type=free_shipping)
6. finalTotal = (subtotal - promo_discount - coupon_discount) + shipping

## Events

| Event | Fired When |
|-------|-----------|
| OrderCreated | After order insert |
| PaymentSucceeded | Callback / mark-paid success |
| PaymentFailed | Callback failure / mismatch |
| OrderStatusChanged | Any status transition |
| OrderCancelled | Status → cancelled |
| AssignedCouponConsumed | After coupon usage committed |
