# Order Feature - API Investigation

## Feature Name

Admin Order List & Detail (read-only)

## Description

Two endpoints under `/api/v1/orders` — paginated list (`index`) and single order detail (`show`). Permission-gated with `VIEW_ORDERS` / `VIEW_ORDER`. Returns `OrderCollection` / `OrderResource` with rich nested relations. The `show` endpoint resolves by primary ID or tracking number.

## Architecture

```
[Admin Client]
    |
    |--- GET /api/v1/orders                  (auth:sanctum + VIEW_ORDERS)
    |--- GET /api/v1/orders/{id}             (auth:sanctum + VIEW_ORDER)
    |
    v
[Order\OrderController]
    |--- index()  -> Order::query()->with(relations)->when(...filters)->paginate()
    |--- show()   -> Order::query()->with(relations)->findOrFail()
    |
    v
[OrderCollection / OrderResource]
    |--- List: data[], links{}
    |--- Detail: id, order_number, status, customer, order_items, transactions, ...
    |
    v
[Models: Order, User, OrderItem, Product, ProductVariant, Transaction, PickupLocation]
```

## Key Endpoints

| Method | URI | Controller Method | Permission | Route Name |
|--------|-----|-------------------|------------|------------|
| GET | `/orders` | `index` | `VIEW_ORDERS` | `orders.index` |
| GET | `/orders/{id}` | `show` | `VIEW_ORDER` | `orders.show` |

## Key Files

| Layer | Path |
|-------|------|
| Controller | `packages/marvel/src/Http/Controllers/Order/OrderController.php` |
| Resource (collection) | `packages/marvel/src/Http/Resources/Order/OrderCollection.php` |
| Resource (item) | `packages/marvel/src/Http/Resources/Order/OrderResource.php` |
| Resource (item child) | `packages/marvel/src/Http/Resources/Order/OrderItemResource.php` |
| Resource (transaction) | `packages/marvel/src/Http/Resources/Order/OrderTransactionResource.php` |
| Model | `packages/marvel/src/Database/Models/Order.php` |
| Enum (Permission) | `packages/marvel/src/Enums/Permission.php` |
| Routes | `packages/marvel/src/Rest/Routes.php` (lines 208–209) |

## Tech Stack

- **Laravel** with Eloquent ORM
- **Sanctum** authentication
- **Spatie permissions** (`VIEW_ORDERS`, `VIEW_ORDER`)
- **API Resources** (`OrderCollection`, `OrderResource`)
- **Pagination** with `?limit=` (default 15, max 100)
- **10 filter parameters** via query string
- **5 eager-loaded relations**
