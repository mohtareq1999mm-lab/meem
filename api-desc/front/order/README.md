# Order Feature - API Investigation

## Feature Name

Order Management

## Description

The Order feature provides complete order lifecycle management — from checkout through payment, fulfillment, and delivery. It spans two domains: a public/authenticated API for customers to view and manage orders, and an admin API for staff to manage the full order lifecycle, process payments, handle refunds, and export data. Includes rich event-driven architecture with notifications, inventory management, and payment gateway integration.

## Architecture Overview

```
[Client]
    |
    |--- GET  /api/v1/general/orders                    (Auth: My Orders)
    |--- POST /api/v1/general/checkout                   (Auth: Create Order)
    |--- POST /api/v1/general/checkout/cod/{id}/mark-paid (Auth: Mark COD Paid)
    |--- ANY  /api/v1/general/checkout/callback          (Public: Gateway)
    |
    |--- GET    /api/v1/orders                           (Admin: List)
    |--- GET    /api/v1/orders/{id}                      (Admin: Detail)
    |--- PUT    /api/v1/orders/{id}                      (Admin: Update Status)
    |--- GET    /api/v1/export-order-url/{shop_id?}      (Admin: Export)
    |--- POST   /api/v1/download-invoice-url             (Admin: Invoice)
    |
    |--- GraphQL: orders, order                         (Queries)
    |--- GraphQL: createOrder, updateOrder, deleteOrder  (Mutations)
    |
    v
[OrderController (General)] or [OrderController (Marvel Admin)]
    |
    v
[OrderService / OrderCreationService / OrderRepository]
    |
    v
[Order Model + OrderProduct + Transaction + Refund]
    |
    v
[orders, order_products, transactions, refunds tables]
```

## Key Endpoints

### Customer API (General)

| Method | URI | Auth |
|--------|-----|------|
| GET | `/v1/general/orders` | `auth:sanctum` |
| POST | `/v1/general/checkout` | `auth:sanctum` |
| POST | `/v1/general/checkout/cod/{orderId}/mark-paid` | `auth:sanctum` + `update-order-status` |
| POST | `/v1/general/checkout/cashier/{orderId}/mark-paid` | `auth:sanctum` + `update-order-status` |
| GET | `/v1/general/checkout/transaction-qr/{uuid}` | `auth:sanctum` |
| ANY | `/v1/general/checkout/callback` | Public |
| GET | `/v1/general/checkout/promotions` | `auth:sanctum` |

### Admin API

| Method | URI | Permission |
|--------|-----|-----------|
| GET | `/v1/orders` | `view-orders` (+ `role:super_admin`) |
| GET | `/v1/orders/{id}` | `view-order` |
| GET | `/v1/orders/tracking-number/{tracking_number}` | Auth |
| POST | `/v1/orders/payment` | Public |
| POST | `/v1/orders/checkout/verify` | Public |
| GET | `/v1/export-order-url/{shop_id?}` | Auth |
| POST | `/v1/download-invoice-url` | Auth |

### GraphQL

| Operation | Resolver |
|-----------|----------|
| `orders` (query) | `OrderQuery@fetchOrders` (paginated) |
| `order` (query) | `OrderQuery@fetchSingleOrder` |
| `createOrder` (mutation) | `OrderMutator@store` |
| `updateOrder` (mutation) | `OrderMutator@update` |
| `deleteOrder` (mutation) | `@delete` with `@can(ability: "super_admin")` |
| `createOrderPayment` (mutation) | `OrderMutator@createOrderPayment` |

## Key Files

| Layer | Path |
|-------|------|
| Controller (General) | `app/Http/Controllers/Api/General/OrderController.php` |
| Controller (Admin) | `packages/marvel/src/Http/Controllers/OrderController.php` |
| Controller (Admin Scoped) | `packages/marvel/src/Http/Controllers/Order/OrderController.php` |
| Model (Order) | `packages/marvel/src/Database/Models/Order.php` |
| Model (OrderProduct) | `packages/marvel/src/Database/Models/OrderProduct.php` |
| Model (Transaction) | `packages/marvel/src/Database/Models/Transaction.php` |
| Model (Refund) | `packages/marvel/src/Database/Models/Refund.php` |
| Repository | `packages/marvel/src/Database/Repositories/OrderRepository.php` |
| Service (Order) | `app/Services/General/OrderService.php` |
| Service (Creation) | `app/Services/Checkout/OrderCreationService.php` |
| Enums (5) | `packages/marvel/src/Enums/OrderStatus.php`, `PaymentStatus.php`, `PaymentGatewayType.php`, `FulfillmentType.php`, `ShippingMethod.php` |
| Events (11) | `packages/marvel/src/Events/` + `app/Events/` |
| Listeners (10) | `packages/marvel/src/Listeners/` + `app/Listeners/` |
| Notifications (4) | `packages/marvel/src/Notifications/` |
| GraphQL Schema | `packages/marvel/src/GraphQL/Schema/models/order.graphql` |
| Export | `packages/marvel/src/Exports/OrderExport.php` |
| Tests | `tests/Feature/OrdersProductionHardenTest.php` (25 tests) |
| Tests | `tests/Feature/OrderCreationFlowTest.php` (18 tests) |

## Tech Stack

- **Laravel** with Eloquent ORM
- **Event-Driven Architecture** — 11 events, 10 listeners (queued)
- **Payment Gateway Integration** — 14 gateway types (Stripe, PayPal, MyFatoorah, etc.)
- **Soft Deletes** for safe order removal
- **Lighthouse PHP** for GraphQL
- **Laravel Excel** for order export
- **Broadcasting** — private channels for real-time order updates
- **Transaction-based checkout** — inventory locking, atomic operations
