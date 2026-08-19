# Frontend - Order Feature

## Status

**No dedicated frontend Vue/React components** found in `resources/js/`. The frontend is a separate SPA.

## Consumption Patterns

### 1. My Orders Page (Customer)

```
GET /api/v1/general/orders?status=pending&limit=15&page=1

Supports `status` filter (pending, processing, completed, cancelled, delivered, refunded, failed, at_local_facility, out_for_delivery, ready_for_pickup) plus `limit` and `page` pagination.

Response:
{
  "data": [
    {
      "id": 1,
      "order_number": "ORD-00000001",
      "status": "pending",
      "total_price": 150.00,
      "payment_method": "cod",
      "shipping_method": "SCHEDULED",
      "fulfillment_type": "delivery",
      "created_at": "2026-07-20T10:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 75, "last_page": 5 }
}
```

### 2. My Order Details Page (Customer)

```
GET /api/v1/general/orders/{id}

Ownership is enforced server-side: the order must belong to the authenticated user,
otherwise 404 is returned (a user can never fetch another user's order by ID).

Response:
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": {
    "id": 1,
    "order_number": "ORD-00000001",
    "status": "pending",
    "total": 120.00,
    "currency": "USD",
    "fulfillment_type": "delivery",
    "payment_method": "cod",
    "created_at": "2026-08-19T14:00:00Z",
    "order_items": [ { "id": 1, "quantity": 2, "unit_price": 100.00, "total_price": 200.00 } ],
    "order_has_invoice": false,
    "invoice_id": null
  }
}
```

### 3. Admin Order Management

```
GET /api/v1/orders?status=pending&search=ORD-00001
GET /api/v1/orders/{id}
PUT /api/v1/orders/{id}  (status update)
```

## What a Frontend Implementation Would Need

```
MyOrdersPage.vue
  Fetches: GET /api/v1/general/orders
  Renders: Order table with status badges
  Features: Search, filter by status, pagination
  Actions: Click row → order detail (GET /api/v1/general/orders/{id})

MyOrderDetailPage.vue
  Fetches: GET /api/v1/general/orders/{id}
  Renders: Full order summary, items, payment & shipping info
  Features: Invoice download via invoice_summary / invoice_id

AdminOrderListPage.vue
  Fetches: GET /api/v1/orders (admin auth)
  Features: Advanced filters, status management, export

AdminOrderDetailPage.vue
  Fetches: GET /api/v1/orders/{id}
  Features: Order items, transactions, status change, invoice download
```

### API Service Layer

```javascript
export const orderApi = {
  myOrders(params)            // GET /api/v1/general/orders
  myOrder(id)                 // GET /api/v1/general/orders/{id}
  checkout(data)              // POST /api/v1/general/checkout
  list(params)                // GET /api/v1/orders (admin)
  show(id)                    // GET /api/v1/orders/{id}
  update(id, data)            // PUT /api/v1/orders/{id}
  markCodPaid(orderId)        // POST /api/v1/general/checkout/cod/{id}/mark-paid
  exportOrders(shopId)        // GET /api/v1/export-order-url/{shop_id}
  downloadInvoice(data)       // POST /api/v1/download-invoice-url
}
```
