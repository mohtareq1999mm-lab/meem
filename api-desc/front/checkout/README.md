# Checkout Module — Frontend (Authenticated + Public API)

## Overview

The Checkout module handles the entire order placement flow: eligible promotions listing, order creation with price recalculation, multiple payment methods (online, COD, pay-at-cashier with QR), payment gateway callbacks, and order status management (COD/cashier mark-as-paid).

## Key Files

| Layer | File |
|-------|------|
| Controller | `app/Http/Controllers/Api/General/OrderController.php` |
| Service | `app/Services/General/OrderService.php` |
| Order Creation | `app/Services/Checkout/OrderCreationService.php` |
| Payment Handler | `app/Services/Payment/PaymentCheckoutHandler.php` |
| Gateway Factory | `app/Services/Payment/PaymentGatewayFactory.php` |
| Cashier QR | `app/Services/Gateway/CashierQrService.php` |
| Collection | `app\Http\Resources\Order\OrderCollection.php` |
| Create Request | `Marvel\Http\Requests\OrderCreateRequest.php` |
| Model (Order) | `Marvel\Database\Models\Order.php` |
| Model (Transaction) | `Marvel\Database\Models\Transaction.php` |
| Routes | `routes/api.php` (lines 77-83) |

## Routes

| Method | Endpoint | Auth | Permission | Purpose |
|--------|----------|------|------------|---------|
| GET | `/api/v1/general/checkout/promotions` | auth:sanctum | — | List eligible promotions |
| POST | `/api/v1/general/checkout` | auth:sanctum | — | Place order |
| POST | `/api/v1/general/checkout/cod/{orderId}/mark-paid` | auth:sanctum | update-order-status | Mark COD as paid |
| POST | `/api/v1/general/checkout/cashier/{orderId}/mark-paid` | auth:sanctum | update-order-status | Mark cashier as paid |
| GET | `/api/v1/general/checkout/transaction-qr/{uuid}` | auth:sanctum | — | Get QR code SVG |
| ANY | `/api/v1/general/checkout/callback` | Public | — | Payment callback (redirect) |
| ANY | `/api/v1/general/checkout/error-callback` | Public | — | Payment error callback (redirect) |

## Dependencies

- **PaymentGatewayFactory** — resolves gateway by name
- **PaymentCheckoutHandler** — online/COD/cashier payment flows
- **OrderCreationService** — order + order items with pricing snapshots
- **CartInventoryService** — reservation, inventory finalization
- **PromotionService** — eligible promotions, promotion application
- **CouponOrchestrator** — coupon validation during checkout
- **ProductPricingService** — real-time price recalculation at checkout
- **Events** — OrderCreated, PaymentSucceeded, PaymentFailed, OrderCancelled, OrderStatusChanged, AssignedCouponConsumed
- **SoftDeletes** — orders are soft-deleted
