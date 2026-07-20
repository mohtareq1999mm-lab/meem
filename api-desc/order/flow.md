# Data Flow - Order Feature

## Flow: Order List (index)

```
Admin Client
  |
  GET /api/v1/orders?status=completed&search=ahmed&limit=15
  Authorization: Bearer <token>
  |
  v
auth:sanctum middleware
  |
  v
permission:VIEW_ORDERS middleware (Spatie)
  |
  v
OrderController@index($request)
  |
  +-- getLimit($request) → 15
  +-- Order::query()
  |     +-- with(['user', 'orderItems.product', 'transactions', ...])
  |     +-- where('status', 'completed')
  |     +-- where(function($q) { $q->where('name','LIKE','%ahmed%')->orWhere(...) })
  |     +-- paginate(15) → LengthAwarePaginator
  |
  v
new OrderCollection($paginator)
  |
  v
JSON Response (data[] + links{})
```

## Flow: Order Detail (show)

```
Admin Client
  |
  GET /api/v1/orders/42
  Authorization: Bearer <token>
  |
  v
auth:sanctum middleware
  |
  v
permission:VIEW_ORDER middleware (Spatie)
  |
  v
OrderController@show($request, '42')
  |
  +-- Order::query()
  |     +-- with([...5 relations...])
  |     +-- findOrFail('42')  -- also works with tracking number
  |
  v
new OrderResource($order)
  |  -- conditionally includes financial/items/transactions fields
  |     via mergeWhen(routeIs('orders.show'), [...])
  v
JSON Response (data{})
```
