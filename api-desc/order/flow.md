# Data Flow - Order Feature

## Flow: Customer Order List (index)

```
Customer App
  |
  GET /api/v1/general/orders?status=completed&limit=15
  Authorization: Bearer <token>
  |
  v
auth:sanctum middleware + throttle:authenticated
  |
  v
App\Http\Controllers\Api\General\OrderController@index
  |
  +-- OrderService::paginateForUser($request)
  |     +-- getLimit() → 15 (default, max 100)
  |     +-- Order::query()->forUser(userId)
  |     +-- when(status) -> where('status', status)
  |     +-- with([orderItems.product(+avg rating, media), productVariant.attributeProducts.attributeValue,
  |               transactions, pickupLocation, latestInvoice])
  |     +-- paginate(15)->withQueryString()
  |     +-- enrich each item's product with pricing
  |
  v
new App\OrderCollection($paginator)  →  App\OrderResource items
  |
  v
JSON Response
  {
    status:200, message:"Data fetched successfully", success:true,
    data: { data:[ OrderResource... ], links:{ current_page, ..., last_page_url, first_page_url } }
  }
```

## Flow: Customer Invoice View (invoice)

```
Customer App
  |
  GET /api/v1/general/orders/invoice/{uuid}
  Authorization: Bearer <token>
  |
  v
auth:sanctum middleware + throttle:authenticated
  |
  v
App\OrderController@invoice
  |
  +-- Invoice::where('uuid', $uuid)->firstOrFail()          → 404 if missing
  +-- if ($invoice->order->user_id !== auth()->id())        → 403 NOT_AUTHORIZED
  |
  v
new CustomerInvoiceResource($invoice)
  |
  v
JSON Response (200): { status, message, success, data: { uuid, invoice_number, status,
  subtotal, shipping_price, total_discount, total, currency, payment_method,
  payment_gateway, generated_at, pdf_generated_at, verification_url, download_url, snapshot } }
```

## Flow: Admin Order List (index)

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
permission:view-orders middleware (Spatie)
  |
  v
Marvel\OrderController@index($request)
  |
  +-- getLimit($request) → 15
  +-- Order::query()
  |     +-- with(['user', 'orderItems.product', 'orderItems.productVariant.attributeProducts.attributeValue', 'transactions', 'pickupLocation'])
  |     +-- where('status', 'completed')
  |     +-- where(function($q) { $q->where('name','LIKE','%ahmed%')->orWhere(...) })
  |     +-- paginate(15) → LengthAwarePaginator
  |
  v
new Marvel\OrderCollection($paginator)
  |
  v
JSON Response (200): { status, message, success, data: { data:[ minimal OrderResource ], links:{} } }
```

## Flow: Admin Order Detail (show)

```
Admin Client
  |
  GET /api/v1/orders/42      (42 = id or tracking number)
  Authorization: Bearer <token>
  |
  v
auth:sanctum middleware
  |
  v
permission:view-order middleware (Spatie)
  |
  v
Marvel\OrderController@show($request, '42')
  |
  +-- Order::query()
  |     +-- with([...5 relations...])
  |     +-- findOrFail('42')  -- also works with tracking number
  |
  v
new Marvel\OrderResource($order)
  |  -- conditionally includes customer_name, financial fields, order_items, transactions
  |     via mergeWhen(routeIs('orders.show'), [...])
  v
JSON Response (200): { status, message, success, data: { full OrderResource } }
```
