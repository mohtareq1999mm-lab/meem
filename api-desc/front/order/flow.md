# Data Flow - Order Feature

## Flow 0: List Orders with Status Filter

```
Client (Auth)
  |
  GET /api/v1/general/orders?status=pending&limit=15&page=1
  |
  v
OrderController@index(Request)
  |
  v
OrderService::paginateForUser($request)
  |
  +-- $limit = $request->get('limit', 15)
  +-- $userId = auth()->user()->id
  |
  +-- Order::query()
  |     |-- forUser($userId)              // WHERE user_id = ?
  |     |-- when(status present)          // WHERE status = 'pending'
  |     |-- with(orderListRelations)      // Eager loads
  |     |-- paginate($limit)              // Paginated result
  |
  v
Response: Paginated orders filtered by status
```

## Flow 1: Checkout → Order Creation

```
Client (Auth)
  |
  POST /api/v1/general/checkout
  Body: { name, user_phone, user_email, address, payment_method, ... }
  |
  v
OrderController@checkout(OrderCreateRequest)
  |
  v
OrderCreationService::createOrder($request, $user)
  |
  +-- DB::transaction():
  |     |-- Validate cart items exist
  |     |-- Lock cart items: lockInventoryRow() (lockForUpdate)
  |     |
  |     |-- Create Order record:
  |     |     |-- user_id, name, phone, email, address
  |     |     |-- subtotal, shipping, total_price
  |     |     |-- coupon/promotion discounts applied
  |     |     |-- status: 'pending'
  |     |
  |     |-- Create OrderProduct records (price snapshots):
  |     |     |-- For each cart item: snapshot product_name, product_sku,
  |     |     |   price, discount_price, flash_sale_price, attributes
  |     |
  |     |-- Handle payment:
  |     |     |-- COD: Transaction(cod, pending)
  |     |     |-- Online: Transaction(pending), redirect to gateway
  |     |     |-- Cashier: Transaction with QR code
  |     |
  |     |-- finalizeOrder():
  |           |-- Increment promotion usage
  |           |-- Update cart status to 'checked_out'
  |           |-- Dispatch OrderCreated event
  |
  v
Response: { order_id, total, message }
```

## Flow 2: Payment Callback (Online)

```
Payment Gateway
  |
  GET /api/v1/general/checkout/callback?paymentId=xxx
  |
  v
OrderController@checkoutCallback(Request)
  |
  v
Gateway::verifyPayment($paymentId)
  |-- Validate signature/amount/currency
  |
  +-- Success:
  |     |-- Transaction: status = 'paid', paid_at = now()
  |     |-- Order: status = 'completed'
  |     |-- Dispatch: PaymentSucceeded
  |     |-- Dispatch: OrderCreated (for notifications)
  |
  +-- Failure:
        |-- Transaction: status = 'failed', error_message logged
        |-- Order: status = 'cancelled'
        |-- Dispatch: PaymentFailed
```

## Flow 3: Admin Status Change

```
Admin
  |
  PUT /api/v1/orders/{id}
  Body: { order_status: "order-completed" }
  |
  v
OrderController@update(OrderUpdateRequest, $id)
  |
  v
OrderManagementTrait::changeOrderStatus($order, $status, $user)
  |
  +-- Validate transition:
  |     |-- pending → [pending, processing, completed, cancelled]
  |     |-- processing → [processing, completed, cancelled]
  |     |-- completed → [completed]
  |     |-- cancelled → [cancelled] (terminal)
  |
  +-- Update legacy order_status column
  +-- syncOrderStatusColumn() → updates modern status column
  +-- manageVendorBalance() → add/deduct shop balance
  +-- Dispatch: OrderStatusChanged (or OrderCancelled / OrderDelivered)
  |
  v
Response: Updated order resource
```
