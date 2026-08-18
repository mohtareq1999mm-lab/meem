# Order Feature - API Investigation

## Feature Name

Order List, Detail & Invoice — **two consumers** (customer frontend app + admin dashboard), read-only list/detail plus customer invoice view.

## Description

Two separate endpoint groups share the name "orders" but have **different controllers, permissions, and response shapes**:

- **Customer** (`/api/v1/general/orders`, `App\Http\Controllers\Api\General\OrderController`):
  - `GET /api/v1/general/orders` — authenticated user's own orders (paginated, `App\Http\Resources\Order\OrderCollection` / `OrderResource`)
  - `GET /api/v1/general/orders/invoice/{uuid}` — owner-only invoice view (`CustomerInvoiceResource`)
- **Admin** (`/api/v1/orders`, `Marvel\Http\Controllers\Order\OrderController`):
  - `GET /api/v1/orders` — paginated list, permission `view-orders` (`Marvel\Http\Resources\Order\OrderCollection`)
  - `GET /api/v1/orders/{id}` — detail by ID or tracking number, permission `view-order` (`Marvel\Http\Resources\Order\OrderResource`)

Both wrap responses in the standard envelope `{ status, message, success, data }` via `Marvel\Traits\ApiResponse`.

## Architecture

```
[Customer App]
    |
    |--- GET /api/v1/general/orders                  (auth:sanctum + throttle:authenticated)
    |--- GET /api/v1/general/orders/invoice/{uuid}   (auth:sanctum + owner-only)
    |
    v
[App\Http\Controllers\Api\General\OrderController]
    |--- index()  -> OrderService::paginateForUser()  -> forUser(userId) + status filter
    |--- invoice() -> Invoice::whereUuid()->firstOrFail(); owner check; CustomerInvoiceResource
    |
    v
[App\Http\Resources\Order\OrderCollection / OrderResource]
    |--- List: order fields + invoice_summary indicator (order_has_invoice / invoice_id)
    |--- Detail: CustomerInvoiceResource (uuid, totals, snapshot, verification_url)

[Admin Dashboard]
    |
    |--- GET /api/v1/orders               (auth:sanctum + throttle:admin + view-orders)
    |--- GET /api/v1/orders/{id}          (auth:sanctum + throttle:admin + view-order)
    |
    v
[Marvel\Http\Controllers\Order\OrderController]
    |--- index()  -> Order::query()->with(relations)->when(...filters)->paginate()
    |--- show()   -> Order::query()->with(relations)->findOrFail()
    |
    v
[Marvel\Http\Resources\Order\OrderCollection / OrderResource]
    |--- List: data[], links{}
    |--- Detail: + customer_name, financial fields, order_items, transactions (routeIs('orders.show'))
    |
    v
[Models: Order, User, OrderItem, Product, ProductVariant, Transaction, PickupLocation]
```

## Key Endpoints

### Customer

| Method | URI | Controller Method | Auth |
|--------|-----|-------------------|------|
| GET | `/api/v1/general/orders` | `index` | Sanctum |
| GET | `/api/v1/general/orders/invoice/{uuid}` | `invoice` | Sanctum + owner |

### Admin

| Method | URI | Controller Method | Permission |
|--------|-----|-------------------|------------|
| GET | `/api/v1/orders` | `index` | `view-orders` |
| GET | `/api/v1/orders/{id}` | `show` | `view-order` |

## Key Files

| Layer | Path |
|-------|------|
| Controller (customer) | `app/Http/Controllers/Api/General/OrderController.php` |
| Controller (admin) | `packages/marvel/src/Http/Controllers/Order/OrderController.php` |
| Resource (customer) | `app/Http/Resources/Order/OrderCollection.php`, `app/Http/Resources/Order/OrderResource.php`, `app/Http/Resources/Order/OrderItemResource.php`, `app/Http/Resources/Order/OrderProductVariantResource.php` |
| Resource (admin) | `packages/marvel/src/Http/Resources/Order/OrderCollection.php`, `OrderResource.php`, `OrderItemResource.php`, `OrderTransactionResource.php` |
| Service (customer list) | `app/Services/General/OrderService.php` (`paginateForUser`) |
| Model | `packages/marvel/src/Database/Models/Order.php` |
| Enum (Permission) | `packages/marvel/src/Enums/Permission.php` |
| Routes (admin) | `packages/marvel/src/Rest/Routes.php` (lines 165–166, loaded under `api/v1`) |
| Routes (customer) | `routes/api.php` (lines 125–126, under `v1/general`) |

## Tech Stack

- **Laravel** with Eloquent ORM
- **Sanctum** authentication
- **Spatie permissions** (`view-orders`, `view-order`) — admin only
- **API Resources** (`OrderCollection`, `OrderResource` in both app and Marvel namespaces)
- **Pagination** with `?limit=` (default 15, max 100)
- **Admin:** 10+ filter parameters; 5 eager-loaded relations
- **Customer:** status filter only; scoped to `forUser`; `latestInvoice` eager-loaded to expose `order_has_invoice` / `invoice_id`