# Request Flows — Checkout Module

## Flow 1: Eligible Promotions

```
GET /checkout/promotions [auth:sanctum]
  → OrderController@eligiblePromotions()
  → OrderService::eligiblePromotionsForUser()
    → getCartUser(): Cart (SCHEDULED items + flash_sales)
    → Cart exists + has items?
      ├─ NO → Response: 400 CART_NOT_FOUND
      └─ YES → PromotionService::eligiblePromotionsPayload($cart)
    → Response: 200 { promotions[], gift_products[] }
```

## Flow 2: Checkout Online

```
POST /checkout { payment_method: "online" } [auth:sanctum]
  → OrderCreateRequest validation
  → getActiveCartForUser → exists? YES
  → ensureCartReservation (lock + sync)
  → COD+pickup? NO
  → OrderService::addItemsInOrder()
    ┌─ DB::transaction
    │  Cart::lockForUpdate (SCHEDULED items)
    │  refreshCartItemPrices (ProductPricingService)
    │  Coupon: lock + validate
    │  calculateCheckoutTotals(promotion, coupon)
    │  Resolve shipping (governorate → price)
    │  OrderCreationService::createOrder
    │  OrderCreationService::createOrderItems
    │  finalizeOrder, finalizeInventory(SCHEDULED)
    └─ DB::commit
  → handleOnlinePayment($order, $amount, $gateway)
    → Gateway::createInvoice(order, amount, callback, error)
    → Transaction::create(status=pending)
  → Response: 200 { url: "https://gateway.com/pay/..." }
```

## Flow 3: Checkout COD

```
POST /checkout { payment_method: "cod" } [auth:sanctum]
  → ... same order creation ...
  → handleCodPayment($order)
    → Transaction::create(payment_method=cod, status=pending)
    → finalizeInventory
  → Response: 200 { order_id }
```

## Flow 4: Checkout Pay at Cashier

```
POST /checkout { payment_method: "pay_at_cashier", fulfillment: "pickup" } [auth:sanctum]
  → ... same order creation ...
  → handleCashierQrPayment($order)
    → Transaction::create(status=pending)
    → CashierQrService::generateBase64DataUri($transaction)
    → finalizeInventory
  → Response: 200 { order_id, transaction_uuid, qr_code }
```

## Flow 5: Payment Callback

```
ANY /callback?paymentId=GTX123 (public)
  → Find Transaction (gateway_transaction_id OR invoice_id)
  → Gateway::verifyPayment(paymentId)
  → GatewayResult:
    ├─ success=true
    ├─ gatewayTransactionId, amount, currency
    └─ rawResponse
  → Update transaction
  → Amount/currency mismatch?
    ├─ YES: cancel order, release cart, redirect /payment/failed
    └─ NO:
      → finalizeItemsByShippingMethod(cart, SCHEDULED)
      → changeOrderStatus(invoice_id, 'completed')
      → event(PaymentSucceeded)
      → Redirect /payment/success?order_id=X
```

## Flow 6: Mark COD/Cashier Paid

```
POST /cod/{id}/mark-paid [auth:sanctum + permission:update-order-status]
  → Order::findOrFail(id)
  → markCodAsPaid($order) or markCashierPaid($order)
    ┌─ DB::transaction
    │  Transaction::lockForUpdate (payment_method, status=pending)
    │  → update: status=paid, paid_at=now
    │  Order::update: status=completed
    │  recordCouponUsage
    │  event(PaymentSucceeded)
    └─ DB::commit
  → Response: 200
```
