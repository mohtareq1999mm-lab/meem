# Changelog - Order Feature

## [Unreleased]

### Added (docs v2)
- Documented the **customer** order endpoints (`/api/v1/general/orders`, `/api/v1/general/orders/invoice/{uuid}`) with true response shapes.
- Replaced admin response examples with source-accurate responses (standard `{ status, message, success, data }` envelope; list excludes `order_items`/`transactions`; detail includes them only on `orders.show`).
- Documented `App\Http\Resources\Order\*` (customer) vs `Marvel\Http\Resources\Order\*` (admin) resource differences.
- Documented `order_has_invoice` / `invoice_id` fields for customer order list.
- Documented customer pagination `links` including `last_page_url` / `first_page_url`.

### Admin (existing)
- Admin order list endpoint `GET /api/v1/orders` with 10 filter parameters
- Admin order detail endpoint `GET /api/v1/orders/{id}` with conditional full detail response
- Permission-based access control (`view-orders` / `view-order`)
- Pagination with configurable limit (default 15, max 100)
- `OrderCollection` + `OrderResource` API resource classes
- 5 eager-loaded relations (user, items/products, variants, transactions, pickup location)
- Dual resolution for detail endpoint (ID or tracking number)

### Known Issues
- No explicit `orderBy` on admin list query
- Promotion name filter uses unindexed LIKE subquery
- `CustomerInvoiceResource.download_url` points to a non-existent route (`/api/v1/general/invoices/{uuid}/download`); real download is `GET /api/v1/invoices/{uuid}/download` (see Invoice docs)