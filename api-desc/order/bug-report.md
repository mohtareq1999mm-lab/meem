# Bug Report - Order Feature

## Issue 1 (LOW): No Explicit Order By on List

- **File:** `packages/marvel/src/Http/Controllers/Order/OrderController.php:55`
- **Description:** `paginate($limit)` applied without `orderBy`. Results depend on default DB ordering (primary key).
- **Impact:** Pagination may return inconsistent ordering across requests.

## Issue 2 (MEDIUM): Promotion Name Subquery Performance

- **File:** `packages/marvel/src/Http/Controllers/Order/OrderController.php:34-38`
- **Description:** `promotion_name` filter uses `whereIn('promotion_code', Promotion::query()->where('name', 'like', "...")->select('code'))`. This is a correlated subquery on a LIKE match with no index on `promotions.name`.
- **Impact:** Slow query on large promotion tables.

## Issue 3 (HIGH, docs-facing): CustomerInvoiceResource.download_url points to non-existent route

- **File:** `app/Http/Resources/Invoice/CustomerInvoiceResource.php:28-30`
- **Description:** `download_url` is generated as `/api/v1/general/invoices/{uuid}/download`, but that route is not registered. The real download endpoint is `GET /api/v1/invoices/{uuid}/download` (registered in `packages/marvel/src/Rest/Routes.php:393`, loaded under `api/v1`).
- **Impact:** Any frontend that follows `download_url` will hit a 404. Frontend should construct the download URL from the invoice uuid instead.

## Issue 4 (INFO): Customer order list does not include invoice_summary

- **File:** `app/Services/General/OrderService.php:82-92`
- **Description:** `paginateForUser()` eager loads `latestInvoice` but not `invoices`, so the `invoice_summary` block in `App\Http\Resources\Order\OrderResource.php:45-58` is never emitted by the list endpoint. Only `order_has_invoice` / `invoice_id` are exposed.
- **Impact:** Frontend cannot show invoice number/total inline from the list; it must open the invoice view endpoint.