# API Reference — Invoice (Frontend Contract)

> **Source of truth:** This document reflects the actual implementation (`app/Http/Controllers/Api/InvoiceController.php`, `packages/marvel/src/Rest/Routes.php`, `routes/api.php`). Where the older documentation contradicts the source, the source wins and the contradiction is reported at the bottom.

---

## Invoice View vs PDF Download vs PDF Preview

There are **three different things** a frontend can do with an invoice. They are **NOT** interchangeable:

| Capability | Endpoint | Returns | Permission |
|-----------|----------|---------|------------|
| **VIEW invoice data** | `GET /api/v1/general/orders/invoice/{uuid}` (customer) or `GET /api/v1/invoices/{id}` (admin) | JSON fields + immutable snapshot (no PDF binary) | Owner (customer) / `view-invoice` (admin) |
| **VERIFY invoice authenticity** | `GET /api/v1/general/invoices/verify/{uuid}` | JSON `{ authentic, invoice, order, qr_content }` | `auth:sanctum` |
| **DOWNLOAD PDF** | `GET /api/v1/invoices/{uuid}/download` | JSON `{ url, invoice_number }` — the PDF file itself is fetched from the returned URL | Owner OR `view-invoice-download` |
| **PDF PREVIEW** | **NOT CURRENTLY PROVIDED** | — | — |

> **PDF Preview:** There is **no dedicated PDF preview endpoint** in the source. The backend does **not** expose a `/preview` route, and the download endpoint does **not** stream PDF bytes — it returns a JSON body containing a storage URL. Any "preview" the frontend offers must be built on top of the `download` URL (e.g. render `{url}` in an `<iframe>`), and it still requires the download authorization rule (`view-invoice-download` for non-owners).

---

## Authentication Rules (source-verified)

| Endpoint | Middleware |
|----------|-----------|
| `GET /api/v1/general/invoices/my-invoices` | `auth:sanctum` |
| `GET /api/v1/general/invoices/uuid/{uuid}` | `auth:sanctum` + `permission:view-invoice` |
| `GET /api/v1/general/invoices/verify/{uuid}` | `auth:sanctum` + `throttle:5,1` |
| `GET /api/v1/general/orders/{orderId}/invoice` | `auth:sanctum` (owner-scoped query; pending order → 404) — **canonical** |
| `GET /api/v1/invoices` | `auth:sanctum` + `permission:view-invoices` |
| `GET /api/v1/invoices/{id}` | `auth:sanctum` + `permission:view-invoice` |
| `GET /api/v1/invoices/{uuid}/download` | `auth:sanctum` + `throttle:30,1` (authorization is **inline** in the controller: owner OR `view-invoice-download`) |
| `POST /api/v1/invoices/{id}/regenerate` | `auth:sanctum` + `permission:regenerate-invoice` |
| `POST /api/v1/invoices/{id}/correct` | `auth:sanctum` + `permission:correct-invoice` |
| `POST /api/v1/invoices/{id}/cancel` | `auth:sanctum` + `permission:cancel-invoice` |
| `POST /api/v1/invoices/{id}/debit-note` | `auth:sanctum` + `permission:issue-debit-note` |

---

### GET /api/v1/general/invoices/my-invoices — My Invoices (Customer)

Customer-facing list of the authenticated user's invoices.

**Authentication**: `auth:sanctum`. No permission middleware (auto-scoped to `user_id`).

**Query Parameters**:

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| limit | int | 15 | Items per page (max 100, `min((int)$request->get('limit', 15), 100)`) |

Eager loads: `order`.

**Response 200**: `CustomerInvoiceCollection` → `{ data: CustomerInvoiceResource[], links: {...} }`

```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": {
    "data": [
      {
        "uuid": "550e8400-...",
        "invoice_number": "INV-2026-000001",
        "status": "ready",
        "subtotal": 100.0,
        "shipping_price": 10.0,
        "total_discount": 5.0,
        "total": 105.0,
        "currency": "EGP",
        "payment_method": "online",
        "payment_gateway": "myfatoorah",
        "generated_at": "2026-07-28T09:00:00+00:00",
        "pdf_generated_at": "2026-07-28T10:00:00+00:00",
        "verification_url": "http://example.com/api/v1/general/invoices/verify/550e8400-...",
        "snapshot": { "...": "see InvoiceSnapshotResource below" }
      }
    ],
    "links": {
      "current_page": 1,
      "from": 1,
      "to": 15,
      "last_page": 1,
      "path": "http://example.com/api/v1/general/invoices/my-invoices",
      "per_page": 15,
      "total": 1,
      "next_page_url": null,
      "prev_page_url": null,
      "last_page_url": "http://example.com/api/v1/general/invoices/my-invoices?page=1",
      "first_page_url": "http://example.com/api/v1/general/invoices/my-invoices?page=1"
    }
  }
}
```

> **Note on `download_url`:** The `download_url` field emitted by `CustomerInvoiceResource` points to `/api/v1/general/invoices/{uuid}/download`, which is **NOT a registered route**. The real download route is `GET /api/v1/invoices/{uuid}/download` (no `/general/`). Frontend must build the download URL as `/api/v1/invoices/{uuid}/download` and must **not** rely on the resource-provided `download_url`. (Reported contradiction.)

---

### GET /api/v1/general/invoices/uuid/{uuid} — Show Invoice by UUID

**Authentication**: `auth:sanctum`, permission: `view-invoice`

Eager loads: `order.orderItems`, `transaction`, `user`.

**Response 200**: `AdminInvoiceResource` (full field set — see resource table below).

**Response 404** (non-existent UUID): default Laravel 404 (from `firstOrFail()`; not the `apiResponse` envelope).

---

### GET /api/v1/general/invoices/verify/{uuid} — Verify Invoice Authenticity

> **Contradiction with older docs:** Older docs described this as a **public** endpoint with `throttle:60,1`. **Actual source:** the route sits inside the `auth:sanctum` group and adds `throttle:5,1`. Verification therefore currently **requires an authenticated Sanctum user**. (Reported contradiction.)

**Authentication**: `auth:sanctum` + `throttle:5,1`

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| uuid | string | Invoice UUID |

**Response 200 (Authentic)**:
```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": {
    "authentic": true,
    "invoice": {},
    "order": {
      "id": 101,
      "order_number": "ORD-001",
      "status": "completed",
      "payment_status": "paid",
      "fulfillment_status": "fulfilled"
    },
    "qr_content": "http://example.com/api/v1/general/invoices/verify/550e8400-..."
  }
}
```

> **Critical known issue:** The `invoice` field is built from `InvoiceResource::make($invoice)`, but `InvoiceResource::toArray()` is **fully commented out** (returns nothing). Serializing it raises `TypeError: ...::toArray(): Return value must be of type array, none returned` → the endpoint **currently fails with HTTP 500** on the authentic path. Frontend must not depend on `data.invoice` until this resource is re-enabled. `qr_content` is the plain verification URL string.

**Response 409 (Tampered)**:
```json
{
  "status": 409,
  "message": "Invoice verification failed",
  "success": false,
  "data": { "authentic": false, "tampered": true }
}
```

**Response 404**: `{ "status": 404, "message": "Not found", "success": false }` (apiResponse envelope).

**Business Rules** (from `InvoiceController::verify()` + `InvoiceService::verifyInvoice()`):
- Verification compares `verification_hash` (computed as `hash('sha256', snapshot_hash . secret)` in `computeVerificationHash()`) against the stored value via `hash_equals()`.
- Authentic path: `verify_count++`, `last_verified_at = now()`, `verified_at` set only on first verification, timeline `verified` event recorded.

---

### GET /api/v1/general/orders/{orderId}/invoice — Customer Invoice by Order ID (canonical)

**Authentication:** `auth:sanctum`. `{orderId}` numeric-only (`whereNumber`).

**Resolution:** ownership is enforced inside the query — `Order::where('user_id', auth id)->findOrFail($orderId)` — then `latestInvoice()`.

- Missing order **or** another user's order → identical **404** handler envelope (no existence leak)
- Pending order (no invoice yet) → `404 { status:404, message:"Not found", success:false }`
- Found → `200` with the same `CustomerInvoiceResource` payload as the legacy route below; returns the correction when one exists (matches what the order list's `invoice_id` advertises)

---

### ~~GET /api/v1/general/orders/invoice/{uuid}~~ — REMOVED

> **Removed (2026-08-22).** Superseded by the canonical Order-ID endpoint above. The route and its `OrderController::invoice()` method no longer exist; requests now fail routing with **404**. Frontends must use `GET /api/v1/general/orders/{orderId}/invoice`.

---

### GET /api/v1/invoices — List Invoices (Admin)

Paginated list of all invoices with filtering, searching, and sorting.

**Authentication**: `auth:sanctum`, permission: `view-invoices`

**Query Parameters**:

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| limit | int | 15 | Items per page (max 100) |
| search | string | - | Search invoice_number or order_number (LIKE) |
| status | string | - | Filter by status |
| order_id | int | - | Filter by order ID |
| user_id | int | - | Filter by user ID |
| invoice_series | string | - | Filter by series (INV) |
| currency | string | - | Filter by currency |
| from | date | - | created_at >= from |
| to | date | - | created_at <= to |
| sort_by | string | created_at | Sort field (created_at, total, status, invoice_number; invalid → created_at) |
| sort_direction | string | desc | asc or desc |

**Response 200**: `AdminInvoiceCollection` → `{ data: AdminInvoiceResource[], links: {...} }`.

---

### GET /api/v1/invoices/{id} — Show Invoice by ID (Admin)

**Authentication**: `auth:sanctum`, permission: `view-invoice`

**Path parameter constraint:** `{id}` is numeric-only (`whereNumber`). Non-numeric ids fail routing with **404** — they never reach the controller.

Eager loads: `order.orderItems`, `transaction`, `user`.

**Response 200**: `AdminInvoiceResource`.

**Response 404** (non-existent id): handler JSON envelope `{"message":"Resource Not Found","status":false}`.

---

### GET /api/v1/invoices/{uuid}/download — Download PDF

> This is the **only** endpoint that authorizes PDF download. It returns a **JSON body with a storage URL**, not the PDF bytes.

**Authentication**: `auth:sanctum`, `throttle:30,1`. No `permission:` middleware — authorization is **inline** in the controller.

**Authorization rule (inline, privacy-first):**
```php
if ($invoice->user_id !== request()->user()->id
    && !request()->user()->can(Permission::VIEW_INVOICE_DOWNLOAD)) {
    return $this->apiResponse(NOT_FOUND, 404, false); // 404, not 403 (do not reveal existence)
}
```

| Who | Result |
|-----|--------|
| Invoice owner (any role, no permission needed) | **200** |
| Non-owner with `view-invoice-download` | **200** |
| Non-owner with `view-invoice` **only** | **404** (DENIED) |
| Non-owner with no permission | **404** (DENIED) |
| Super admin (seeded with `view-invoice-download`) | **200** |

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| uuid | string | Invoice UUID (route constrained with `whereUuid`) |

**Response 200**:
```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": {
    "url": "http://example.com/storage/invoices/INV-2026-000001.pdf",
    "invoice_number": "INV-2026-000001"
  }
}
```

The `url` is `url('storage/invoices/' . $invoice->pdf_path)`. The PDF is stored on the `public` disk at `storage/app/public/invoices/{filename}.pdf` (filename = `invoice_number` with `/` → `-`, plus `.pdf`). The storage symlink must be present for the URL to resolve.

**Response 404 (not found / unauthorized — same envelope, privacy)**:
```json
{ "status": 404, "message": "Not found", "success": false }
```

**Response 404 (PDF not yet generated)**:
```json
{
  "status": 404,
  "message": "PDF not yet generated",
  "success": false,
  "data": {
    "status": "pdf_generating",
    "pdf_generated_at": null
  }
}
```

**Business Rules / Side Effects**:
- `downloaded_at` is set only on the **first** download (`$invoice->downloaded_at ?? now()`); repeat downloads keep the original value.
- Timeline event `downloaded` is recorded on **every** successful download.
- Status is **not** changed by download (the `downloaded` status is a separate lifecycle concept; the PDF job sets `ready`).

---

### POST /api/v1/invoices/{id}/regenerate — Regenerate PDF (Admin)

**Authentication**: `auth:sanctum`, permission: `regenerate-invoice`

**Path Parameters**: `id` (int).

**Response 200**:
```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": { "invoice_id": 1, "status": "pdf_generating" }
}
```

**Response 422** (invalid status): `{ status: 422, message: "...", success: false }` (uses `ERROR_ADDING_ITEMS_TO_ORDER` message).

**Business Rules**:
- Allowed only from `failed`, `ready`, `generated`.
- Sets status → `pdf_generating`, increments `generation_attempts`, clears `last_generation_error`.
- Records timeline `pdf_regenerated`.
- Dispatches `GenerateInvoicePdfJob` (queue `meem-medium`, tries 3, backoff [30,120,300], timeout 120s).

> **State machine note:** `READY → PDF_GENERATING` is legal (`InvoiceStatus` enum). The controller allowlist and enum agree — regenerating a `ready` invoice is fully supported end-to-end.
>
> **404 behavior:** non-existent id → `{"message":"Resource Not Found","status":false}` (HTTP 404). Malformed (non-numeric) id → route-level 404.

---

### POST /api/v1/invoices/{id}/correct — Correct Invoice (Admin)

**Authentication**: `auth:sanctum`, permission: `correct-invoice`

**Request Body** (validated by `CorrectInvoiceRequest`):

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| reason | string | required | Correction reason (max 500) |
| overrides | object | sometimes | Field overrides |
| overrides.total | numeric | sometimes | Override total |
| overrides.amount_paid | numeric | sometimes | Override amount paid |
| overrides.shipping_price | numeric | sometimes | Override shipping price |
| overrides.customer.name | string | sometimes | Override customer name |
| overrides.customer.email | email | sometimes | Override customer email |
| overrides.customer.phone | string | sometimes | Override customer phone |
| overrides.billing_address | array | sometimes | Override billing address |
| overrides.shipping_address | array | sometimes | Override shipping address |
| overrides.notes | string | sometimes | Override notes |

**Response 200**: `{ status, message: "Invoice corrected successfully", success, data: AdminInvoiceResource(correction) }`

**Response 422** (invalid status): `{ status: 422, message: "Invoice 1 cannot be corrected from status 'cancelled'", success: false }`

**Response 404** (non-existent id): `{"message":"Resource Not Found","status":false}` — no internal class names are exposed.

**Business Rules**:
- Allowed only from `generated`, `ready`, `verified`, `downloaded`, `printed`.
- Creates a **new** invoice (correction) with a new invoice number, `is_correction = true`, `correction_to_id = original.id`, status `generated`.
- Original invoice → status `corrected` + `corrected_at` + `correction_reason`.
- Overrides applied to snapshot via `data_set()`; snapshot re-hashed.
- Timeline: `recordCorrected(original)` + `recordGenerated(correction)`.
- After commit: dispatches `InvoiceCreated` + `GenerateInvoicePdfJob` for the correction.

---

### POST /api/v1/invoices/{id}/cancel — Cancel Invoice (Admin)

**Authentication**: `auth:sanctum`, permission: `cancel-invoice`

**Request Body**: `reason` (string, required, max 500) — validated inline via `$request->validate()`.

**Response 200**: `{ status, message: "Invoice cancelled successfully", success, data: AdminInvoiceResource(fresh) }`

**Response 422** (invalid status): `{ status: 422, message: "Invoice 1 cannot be cancelled from status 'archived'", success: false }`

**Response 404** (non-existent id): `{"message":"Resource Not Found","status":false}` — no internal class names are exposed.

**Business Rules**:
- Allowed only from `generated`, `ready`, `failed`, `corrected`, `verified`, `downloaded`, `printed`.
- Sets `status=cancelled`, `cancelled_at`, `cancellation_reason`.
- Uses `lockForUpdate` inside transaction; timeline `cancelled` recorded.

---

### POST /api/v1/invoices/{id}/debit-note — Issue Debit Note (Admin)

**Authentication**: `auth:sanctum`, permission: `issue-debit-note`

**Request Body** (validated by `DebitNoteRequest`): `amount` (numeric, required, min 0.01), `reason` (string, required, max 500).

**Response 201**: `{ status: 201, message: "Debit note issued successfully", success: true, data: { ...DebitNote attributes... } }` — the raw `DebitNote` model serialized (uuid, debit_note_number, debit_note_series, sequence_number, sequence_year, reason, type, amount, currency, created_by, line_items, notes, issued_at, timestamps). Not wrapped in a resource.

**Response 422** (invalid status): `{ status: 422, message: "Cannot issue debit note for invoice in status: archived", success: false }`

**Business Rules**:
- Allowed only from `generated`, `ready`, `verified`, `downloaded`, `printed`.
- Auto-numbered via `InvoiceNumberService` (`DN` series).
- Transactional creation.

---

## Resource Structures (source-verified)

### AdminInvoiceResource — used by `index`, `show`, `showByUuid`, `correct`, `cancel`

| Field | Type | Condition |
|-------|------|-----------|
| id | int | Always |
| uuid | string | Always |
| order_id | int | Always |
| invoice_number | string | Always |
| status | string | Always |
| subtotal | float (rounded 2dp) | Always |
| shipping_price | float (rounded 2dp) | Always |
| coupon_discount | float (rounded 2dp) | Always |
| promotion_discount | float (rounded 2dp) | Always |
| total_discount | float (rounded 2dp) | Always |
| total | float (rounded 2dp) | Always |
| amount_paid | float (rounded 2dp) | Always |
| currency | string | Always |
| payment_method | string | Always |
| payment_gateway | string | Always |
| snapshot_hash | string | Always |
| verification_hash | string | Always |
| pdf_generated_at | string (ISO8601) | Always |
| generated_at | string (ISO8601) | Always |
| generation_attempts | int | Always (default 0) |
| last_generation_error | string/null | Always |
| is_correction | bool | Always |
| correction_reason | string/null | Always |
| corrected_at | string/null | Always |
| cancelled_at | string/null | Always |
| cancellation_reason | string/null | Always |
| verified_at | string/null | Always |
| downloaded_at | string/null | Always |
| printed_at | string/null | Always |
| archived_at | string/null | Always |
| last_verified_at | string/null | Always |
| verify_count | int | Always (default 0) |
| created_at | string (ISO8601) | Always |
| verification_url | string | When uuid |
| qr_content | object `{uuid, invoice_number, verification_hash, issued_at, verification_url}` | When uuid |
| download_url | string | When uuid AND pdf_path |
| snapshot | InvoiceSnapshotResource | When data |
| timeline | array (last 10) `{event, old_status, new_status, created_at}` | When relation loaded |
| credit_notes_summary | object `{count, total_amount}` | When relation loaded |
| debit_notes_summary | object `{count, total_amount}` | When relation loaded |

> **Note:** `AdminInvoiceResource.download_url` emits `/api/v1/general/invoices/{uuid}/download` which is **not a registered route**. Use `GET /api/v1/invoices/{uuid}/download`.

### CustomerInvoiceResource — used by `myInvoices` and `GET /orders/invoice/{uuid}`

| Field | Type | Condition |
|-------|------|-----------|
| uuid | string | Always |
| invoice_number | string | Always |
| status | string | Always |
| subtotal | float (rounded 2dp) | Always |
| shipping_price | float (rounded 2dp) | Always |
| total_discount | float (rounded 2dp) | Always |
| total | float (rounded 2dp) | Always |
| currency | string | Always |
| payment_method | string | Always |
| payment_gateway | string | Always |
| generated_at | string (ISO8601) | Always |
| pdf_generated_at | string (ISO8601) | Always |
| verification_url | string | When uuid |
| download_url | string | When uuid AND pdf_path |
| snapshot | InvoiceSnapshotResource | When data |

> **Note:** Customer resource exposes **no** `id`, `order_id`, `amount_paid`, `coupon_discount`, `promotion_discount`, hashes, or lifecycle timestamps.

### InvoiceSnapshotResource — the frozen order snapshot

Sections: `snapshot_version`, `snapshot_schema`, `order` `{id, order_number, status, payment_status, fulfillment_status}`, `customer` `{name}`, `billing_address`, `shipping_address`, `fulfillment` `{type, shipping_method, shipping_price, fast_shipping_fee, expected_delivery_at}`, `pickup_location`, `items[]` `{product_name, product_sku, attributes, quantity, unit_price, total_price, is_gift}`, `pricing_breakdown` `{subtotal, promotion_discount, coupon_discount, shipping_price, fast_shipping_fee, total, currency}`, `payment` `{method, gateway, paid_at}`, `metadata`, `audit` `{generated_by, generated_at}`.

### Collections

`AdminInvoiceCollection`, `CustomerInvoiceCollection`, `InvoiceCollection` all return `{ data: [...], links: { current_page, from, to, last_page, path, per_page, total, next_page_url, prev_page_url, last_page_url, first_page_url } }`.

> **Note:** `InvoiceResource` / `InvoiceCollection` currently have their `toArray()` bodies commented out. They are only referenced by `verify()` and are effectively broken (see the verify known issue). All live list/show/correct/cancel endpoints use the `Admin*`/`Customer*` resources.

---

## Reported Contradictions (source is authoritative)

1. **Verify is not public.** Older docs said `GET /verify/{uuid}` is public with `throttle:60,1`. Actual route: `auth:sanctum` + `throttle:5,1`.
2. **Customer invoice URLs.** Older docs used `/api/v1/invoices/my-invoices`, `/api/v1/invoices/verify/{uuid}`, `/api/v1/invoices/uuid/{uuid}`, `/api/v1/orders/invoice/{uuid}`. Actual routes are under `/api/v1/general/...`.
3. **`download_url` is broken in resources.** Both `AdminInvoiceResource` and `CustomerInvoiceResource` emit `download_url` = `/api/v1/general/invoices/{uuid}/download`, which is **not a registered route**. The real download route is `/api/v1/invoices/{uuid}/download`.
4. **`InvoiceResource` is disabled.** `InvoiceResource::toArray()` is fully commented out; `verify()` fails with `TypeError` (HTTP 500) on the authentic path.
5. **Download authorization is inline**, not a `permission:` middleware (route shows `[auth:sanctum, throttle:30,1]` only). Behavior matches the documented owner-OR-`view-invoice-download` rule.
6. **Admin routes live in the package**, not `routes/api.php`: `packages/marvel/src/Rest/Routes.php` (lines 390-399), loaded under `api/v1` by `RestApiServiceProvider`. Customer routes live in `routes/api.php` (lines 133-137) inside the `v1/general` prefix.
