# API Reference â€” Invoice (Frontend Contract)

> **Source of truth:** This document reflects the actual implementation (`app/Http/Controllers/Api/InvoiceController.php`, `packages/marvel/src/Rest/Routes.php`, `routes/api.php`). Where the older documentation contradicts the source, the source wins and the contradiction is reported at the bottom.

---

## Invoice View vs PDF Download vs PDF Preview

There are **three different things** a frontend can do with an invoice. They are **NOT** interchangeable:

| Capability | Endpoint | Returns | Permission |
|-----------|----------|---------|------------|
| **VIEW invoice data** | `GET /api/v1/general/orders/invoice/{uuid}` (customer) or `GET /api/v1/invoices/{id}` (admin) | JSON fields + immutable snapshot (no PDF binary) | Owner (customer) / `view-invoice` (admin) |
| **VERIFY invoice authenticity** | `GET /api/v1/general/invoices/verify/{uuid}` | JSON `{ authentic, invoice, order, qr_content }` | `auth:sanctum` |
| **DOWNLOAD PDF** | `GET /api/v1/invoices/{uuid}/download` | JSON `{ url, invoice_number }` â€” the PDF file itself is fetched from the returned URL | Owner OR `view-invoice-download` |
| **PDF PREVIEW** | **NOT CURRENTLY PROVIDED** | â€” | â€” |

> **PDF Preview:** There is **no dedicated PDF preview endpoint** in the source. The backend does **not** expose a `/preview` route, and the download endpoint does **not** stream PDF bytes â€” it returns a JSON body containing a storage URL. Any "preview" the frontend offers must be built on top of the `download` URL (e.g. render `{url}` in an `<iframe>`), and it still requires the download authorization rule (`view-invoice-download` for non-owners).

---

## Authentication Rules (source-verified)

| Endpoint | Middleware |
|----------|-----------|
| `GET /api/v1/general/invoices/my-invoices` | `auth:sanctum` |
| `GET /api/v1/general/invoices/show/uuid/{uuid}` | `auth:sanctum` (`whereUuid`; owner-scoped, 403 non-owner) - returns canonical `CustomerInvoiceResource` |
| `GET /api/v1/general/invoices/uuid/{uuid}` | `auth:sanctum` + `permission:view-invoice` |
| `GET /api/v1/general/invoices/verify/{uuid}` | `auth:sanctum` + `throttle:5,1` |
| `GET /api/v1/general/orders/{orderId}/invoice` | `auth:sanctum` (owner-scoped query; pending order -> 404) - **canonical** |
| `GET /api/v1/invoices` | `auth:sanctum` + `permission:view-invoices` (controller) |
| `GET /api/v1/invoices/{id}` | `auth:sanctum` + `permission:view-invoice` (controller) |
| `GET /api/v1/invoices/{uuid}/download` | `auth:sanctum` + `throttle:30,1` (authorization is **inline** in the controller: owner OR `view-invoice-download`) |
| `POST /api/v1/invoices/{id}/regenerate` | `auth:sanctum` + `permission:regenerate-invoice` (controller) |
| `POST /api/v1/invoices/{id}/correct` | `auth:sanctum` + `permission:correct-invoice` (controller) |
| `POST /api/v1/invoices/{id}/cancel` | `auth:sanctum` + `permission:cancel-invoice` (controller) |
| `POST /api/v1/invoices/{id}/debit-note` | `auth:sanctum` + `permission:issue-debit-note` (controller) |
| `GET /api/v1/invoices/verify/{uuid}` | `auth:sanctum` + `throttle:5,1` (**no** permission middleware) |
| `GET /api/v1/invoices/uuid/{uuid}` | `auth:sanctum` + `permission:view-invoice` (controller, same as `{id}` show) |

## Route Registration Map (source: `packages/marvel/src/Rest/Routes.php:391-403` + `routes/api.php:133-137`)

All invoice actions resolve to ONE controller: `App\Http\Controllers\Api\InvoiceController`. Two prefixes expose it:

| Prefix | Source | Scope | Routes |
|--------|--------|-------|--------|
| `/api/v1/general/invoices` | `routes/api.php:134-138` | customer | `my-invoices`, `verify/{uuid}` (`throttle:5,1`), `uuid/{uuid}` |
| `/api/v1/invoices` | `Routes.php:391-403`, group `auth:sanctum` | admin/staff | `/` index; `{uuid}/download` (`whereUuid`, `throttle:30,1`); `{id}` show (`whereNumber`); `{id}/regenerate|correct|cancel|debit-note` (`whereNumber`); `verify/{uuid}` (`throttle:5,1`); `uuid/{uuid}` |

Constraints: UUIDs enforced by `whereUuid`, numeric IDs by `whereNumber`. No route-level `permission:` middleware exists anywhere in the group - all permission checks are controller-constructor level (see table above).

---

### GET /api/v1/general/invoices/my-invoices â€” My Invoices (Customer)

Customer-facing list of the authenticated user's invoices.

**Authentication**: `auth:sanctum`. No permission middleware (auto-scoped to `user_id`).

**Query Parameters**:

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| limit | int | 15 | Items per page (max 100, `min((int)$request->get('limit', 15), 100)`) |

Eager loads: `order`.

**Response 200**: `CustomerInvoiceCollection` â†’ `{ data: CustomerInvoiceListResource[], links: {...} }`

**Lightweight list contract (v1.7.0):** items contain ONLY invoice-level summary fields â€” **no `snapshot`** and none of its sub-objects.

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
        "view_url": "http://example.com/api/v1/general/invoices/show/uuid/550e8400-...",
        "download_url": "http://example.com/api/v1/invoices/550e8400-.../download"
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

> **`download_url` note:** the list resource now emits the **registered** route `/api/v1/invoices/{uuid}/download`. (The older customer resource emitted a non-existent `/general/.../download` path â€” see changelog 1.7.0; detail endpoints still use the legacy resource until separately approved.)
>
> **Full details:** the snapshot remains available on the detail endpoint `GET /orders/{orderId}/invoice` (`CustomerInvoiceResource`, incl. snapshot) â€” the list is intentionally summary-only.

---

### GET /api/v1/general/invoices/show/uuid/{uuid} â€” Customer Invoice by UUID

Customer-facing single-invoice view. Lets the authenticated user open **her own invoice** directly by its UUID and returns the **canonical `CustomerInvoiceResource`** - byte-identical response shape to the Order-based endpoint `GET /api/v1/general/orders/{orderId}/invoice`.

Source: route `routes/api.php` (`invoices` group, `show/uuid/{uuid}` + `whereUuid`) -> controller `App\Http\Controllers\Api\InvoiceController@showByUuidForUser`.

#### Authentication

| Aspect | Detail |
|--------|--------|
| Required | Yes |
| Guard | `auth:sanctum` |
| Permission | None (ownership enforced in-controller; no constructor entry for this action) |

#### Path Parameters

| Parameter | Type | Constraint | Description |
|-----------|------|------------|-------------|
| `uuid` | string | `whereUuid` | Invoice UUID. Malformed UUIDs fail routing with **404** and never reach the controller |

#### Ownership & Security

- Eager loads `order.orderItems`, `transaction`, `user`, then resolves via `firstOrFail()` -> missing uuid = **404**.
- Non-owner: `$invoice->order->user_id !== $request->user()->id` throws `AuthorizationException` -> **403** `NOT_AUTHORIZED`.
- No permission required - any authenticated user may open her OWN invoice; nobody else's.

#### Response 200 (identical shape to `GET /general/orders/{orderId}/invoice`)

```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": {
        "uuid": "550e8400-e29b-41d4-a716-446655440000",
        "invoice_number": "INV-2026-000001",
        "status": "generated",
        "subtotal": 100.0,
        "shipping_price": 30.0,
        "total_discount": 0.0,
        "total": 130.0,
        "currency": "EGP",
        "payment_method": "cod",
        "payment_gateway": null,
        "generated_at": "2026-08-22T09:00:00+00:00",
        "pdf_generated_at": null,
        "verification_url": "http://example.com/api/v1/general/invoices/verify/550e8400-e29b-41d4-a716-446655440000",
        "view_url": "http://example.com/api/v1/general/invoices/show/uuid/550e8400-...",
        "snapshot": { "...": "InvoiceSnapshotResource when invoice.data exists" }
    }
}
```

> **Detail â‰  List (since v1.7.0).** This detail response keeps the full `snapshot`; the `my-invoices` list uses the lightweight `CustomerInvoiceListResource` with no snapshot. Its `download_url` also differs: detail keeps the legacy broken path caveat, while the list emits the registered `/api/v1/invoices/{uuid}/download`.

#### Error Responses

| Status | When | Body |
|--------|------|------|
| `401` | Unauthenticated | standard envelope |
| `403` | Authenticated but NOT the invoice owner (`NOT_AUTHORIZED`) | standard error envelope |
| `404` | Unknown uuid OR malformed uuid (route constraint) | Laravel 404 / default handler |

#### Tests

`tests/Feature/CustomerInvoiceByUuidTest.php` (5 passing): owner 200 + resource shape, non-owner 403, guest 401, unknown uuid 404, malformed uuid 404 at routing layer.

---

### GET /api/v1/general/invoices/uuid/{uuid} â€” Show Invoice by UUID

**Authentication**: `auth:sanctum`, permission: `view-invoice`

Eager loads: `order.orderItems`, `transaction`, `user`.

**Response 200**: `AdminInvoiceResource` (full field set â€” see resource table below).

**Response 404** (non-existent UUID): default Laravel 404 (from `firstOrFail()`; not the `apiResponse` envelope).

---

### GET /api/v1/general/invoices/verify/{uuid} â€” Verify Invoice Authenticity

> **Contradiction with older docs:** Older docs described this as a **public** endpoint with `throttle:60,1`. **Actual source:** the route sits inside the `auth:sanctum` group and adds `throttle:5,1`. Verification therefore currently **requires an authenticated Sanctum user**. (Reported contradiction.)

**Authentication**: `auth:sanctum` + `throttle:5,1`

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| uuid | string | Invoice UUID |

**Response 200 (Authentic)** â€” `data.invoice` is a full `InvoiceResource` (restored 2026-08-22):
```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": {
        "authentic": true,
        "invoice": {
            "id": 1,
            "uuid": "550e8400-â€¦",
            "order_id": 101,
            "invoice_number": "INV-2026-000001",
            "status": "ready",
            "subtotal": 200.0,
            "shipping_price": 30.0,
            "total_discount": 20.0,
            "total": 210.0,
            "amount_paid": 210.0,
            "currency": "EGP",
            "is_correction": false,
            "verify_count": 1,
            "verification_url": "http://example.com/api/v1/general/invoices/verify/550e8400-â€¦",
            "view_url": "http://example.com/api/v1/general/invoices/show/uuid/550e8400-...",
            "created_at": "2026-07-20T10:35:00+00:00"
        },
        "order": {
            "id": 101,
            "order_number": "ORD-001",
            "status": "completed",
            "payment_status": "paid",
            "fulfillment_status": "fulfilled"
        },
        "qr_content": "http://example.com/api/v1/general/invoices/verify/550e8400-â€¦"
    }
}
```

**Business Rules** (from `InvoiceController::verify()` + `InvoiceService::verifyInvoice()`):
- Verification compares `verification_hash` (computed as `hash('sha256', snapshot_hash . secret)` in `computeVerificationHash()`) against the stored value via `hash_equals()`.
- Authentic path: `verify_count++`, `last_verified_at = now()`, `verified_at` set only on first verification, timeline `verified` event recorded.

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

---

### GET /api/v1/general/orders/{orderId}/invoice â€” Customer Invoice by Order ID (canonical)

**Authentication:** `auth:sanctum`. `{orderId}` numeric-only (`whereNumber`).

**Resolution:** ownership is enforced inside the query â€” `Order::where('user_id', auth id)->findOrFail($orderId)` â€” then `latestInvoice()`.

- Missing order **or** another user's order â†’ identical **404** handler envelope (no existence leak)
- Pending order (no invoice yet) â†’ `404 { status:404, message:"Not found", success:false }`
- Found â†’ `200` with the same `CustomerInvoiceResource` payload as the legacy route below; returns the correction when one exists (matches what the order list's `invoice_id` advertises)

**Response 200 (trimmed):**
```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": {
        "uuid": "550e8400-â€¦",
        "invoice_number": "INV-2026-000001",
        "status": "ready",
        "total": 210.0,
        "currency": "EGP",
        "verification_url": "http://example.com/api/v1/general/invoices/verify/550e8400-â€¦",
        "view_url": "http://example.com/api/v1/general/invoices/show/uuid/550e8400-...",
        "download_url": "http://example.com/api/v1/general/invoices/550e8400-â€¦/download",
        "snapshot": { "â€¦": "full frozen snapshot" }
    }
}
```

---

### ~~GET /api/v1/general/orders/invoice/{uuid}~~ â€” REMOVED

> **Removed (2026-08-22).** Superseded by the canonical Order-ID endpoint above. The route and its `OrderController::invoice()` method no longer exist; requests now fail routing with **404**. Frontends must use `GET /api/v1/general/orders/{orderId}/invoice`.

---

### GET /api/v1/invoices â€” List Invoices (Admin)

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
| sort_by | string | created_at | Sort field (created_at, total, status, invoice_number; invalid â†’ created_at) |
| sort_direction | string | desc | asc or desc |

**Response 200**: `AdminInvoiceCollection` â†’ `{ data: AdminInvoiceResource[], links: {...} }`.

---

### GET /api/v1/invoices/{id} â€” Show Invoice by ID (Admin)

**Authentication**: `auth:sanctum`, permission: `view-invoice`

**Path parameter constraint:** `{id}` is numeric-only (`whereNumber`). Non-numeric ids fail routing with **404** - they never reach the controller.

Eager loads: `order.orderItems`, `transaction`, `user`.

**Response 200**: `AdminInvoiceResource`.

**Response 404** (non-existent id): handler JSON envelope `{"message":"Resource Not Found","status":false}`.

---

### GET /api/v1/invoices/verify/{uuid} â€” Verify Invoice Authenticity (Admin prefix)

Registered at `packages/marvel/src/Rest/Routes.php:400` inside the `auth:sanctum` group with `throttle:5,1`. Resolves to the SAME controller action as the customer route `GET /api/v1/general/invoices/verify/{uuid}` - identical request/response behavior, including the known `InvoiceResource` TypeError on the authentic path (see Reported Contradictions #4).

**Authentication**: `auth:sanctum` + `throttle:5,1` (**no** permission middleware on this action).

**Path parameter constraint:** `{uuid}` is UUID-only (`whereUuid`).

See "GET /api/v1/general/invoices/verify/{uuid}" above for full response shapes.

---

### GET /api/v1/invoices/uuid/{uuid} â€” Show Invoice by UUID (Admin prefix)

Registered at `packages/marvel/src/Rest/Routes.php:401`. Resolves to the SAME controller action (`showByUuid`) as `GET /api/v1/general/invoices/uuid/{uuid}`; permission `view-invoice` is enforced controller-level for both prefixes.

Eager loads: `order.orderItems`, `transaction`, `user`.

**Response 200**: `AdminInvoiceResource`.

**Response 404**: default Laravel 404 from `firstOrFail()`.

---

### GET /api/v1/invoices/{uuid}/download â€” Download PDF

> This is the **only** endpoint that authorizes PDF download. It returns a **JSON body with a storage URL**, not the PDF bytes.

**Authentication**: `auth:sanctum`, `throttle:30,1`. No `permission:` middleware â€” authorization is **inline** in the controller.

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

The `url` is `url('storage/invoices/' . $invoice->pdf_path)`. The PDF is stored on the `public` disk at `storage/app/public/invoices/{filename}.pdf` (filename = `invoice_number` with `/` â†’ `-`, plus `.pdf`). The storage symlink must be present for the URL to resolve.

**Response 404 (not found / unauthorized â€” same envelope, privacy)**:
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

### POST /api/v1/invoices/{id}/regenerate â€” Regenerate PDF (Admin)

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
- Sets status â†’ `pdf_generating`, increments `generation_attempts`, clears `last_generation_error`.
- Records timeline `pdf_regenerated`.
- Dispatches `GenerateInvoicePdfJob` (queue `meem-medium`, tries 3, backoff [30,120,300], timeout 120s).

> **State machine note:** `READY â†’ PDF_GENERATING` is legal (`InvoiceStatus` enum). The controller allowlist and enum agree â€” regenerating a `ready` invoice is fully supported end-to-end.
>
> **404 behavior:** non-existent id â†’ `{"message":"Resource Not Found","status":false}` (HTTP 404). Malformed (non-numeric) id â†’ route-level 404.

---

### POST /api/v1/invoices/{id}/correct â€” Correct Invoice (Admin)

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

**Response 404** (non-existent id): `{"message":"Resource Not Found","status":false}` â€” no internal class names are exposed.

**Business Rules**:
- Allowed only from `generated`, `ready`, `verified`, `downloaded`, `printed`.
- Creates a **new** invoice (correction) with a new invoice number, `is_correction = true`, `correction_to_id = original.id`, status `generated`.
- Original invoice â†’ status `corrected` + `corrected_at` + `correction_reason`.
- Overrides applied to snapshot via `data_set()`; snapshot re-hashed.
- Timeline: `recordCorrected(original)` + `recordGenerated(correction)`.
- After commit: dispatches `InvoiceCreated` + `GenerateInvoicePdfJob` for the correction.

---

### POST /api/v1/invoices/{id}/cancel â€” Cancel Invoice (Admin)

**Authentication**: `auth:sanctum`, permission: `cancel-invoice`

**Request Body**: `reason` (string, required, max 500) â€” validated inline via `$request->validate()`.

**Response 200**: `{ status, message: "Invoice cancelled successfully", success, data: AdminInvoiceResource(fresh) }`

**Response 422** (invalid status): `{ status: 422, message: "Invoice 1 cannot be cancelled from status 'archived'", success: false }`

**Response 404** (non-existent id): `{"message":"Resource Not Found","status":false}` â€” no internal class names are exposed.

**Business Rules**:
- Allowed only from `generated`, `ready`, `failed`, `corrected`, `verified`, `downloaded`, `printed`.
- Sets `status=cancelled`, `cancelled_at`, `cancellation_reason`.
- Uses `lockForUpdate` inside transaction; timeline `cancelled` recorded.

---

### POST /api/v1/invoices/{id}/debit-note â€” Issue Debit Note (Admin)

**Authentication**: `auth:sanctum`, permission: `issue-debit-note`

**Request Body** (validated by `DebitNoteRequest`): `amount` (numeric, required, min 0.01), `reason` (string, required, max 500).

**Response 201**: `{ status: 201, message: "Debit note issued successfully", success: true, data: { ...DebitNote attributes... } }` â€” the raw `DebitNote` model serialized (uuid, debit_note_number, debit_note_series, sequence_number, sequence_year, reason, type, amount, currency, created_by, line_items, notes, issued_at, timestamps). Not wrapped in a resource.

**Response 422** (invalid status): `{ status: 422, message: "Cannot issue debit note for invoice in status: archived", success: false }`

**Business Rules**:
- Allowed only from `generated`, `ready`, `verified`, `downloaded`, `printed`.
- Auto-numbered via `InvoiceNumberService` (`DN` series).
- Transactional creation.

---

## Resource Structures (source-verified)

### AdminInvoiceResource â€” used by `index`, `show`, `showByUuid`, `correct`, `cancel`

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
| view_url | string | When order_id â€” `/api/v1/invoices/{id}` admin viewer |
| qr_content | object `{uuid, invoice_number, verification_hash, issued_at, verification_url}` | When uuid |
| download_url | string | When uuid AND pdf_path |
| snapshot | InvoiceSnapshotResource | When data |
| timeline | array (last 10) `{event, old_status, new_status, created_at}` | When relation loaded |
| credit_notes_summary | object `{count, total_amount}` | When relation loaded |
| debit_notes_summary | object `{count, total_amount}` | When relation loaded |

> **Note:** `AdminInvoiceResource.download_url` emits `/api/v1/general/invoices/{uuid}/download` which is **not a registered route**. Use `GET /api/v1/invoices/{uuid}/download`.

### CustomerInvoiceListResource â€” used by `myInvoices` (list)

Lightweight summary only (v1.7.0): `uuid, invoice_number, status, subtotal, shipping_price, total_discount, total, currency, payment_method, payment_gateway, generated_at, pdf_generated_at, verification_url, view_url, download_url`. **No snapshot.** `download_url` points to the registered `/api/v1/invoices/{uuid}/download`; `view_url` to `/api/v1/general/orders/{order_id}/invoice`.

### CustomerInvoiceResource â€” used by `GET /general/orders/{orderId}/invoice`, `GET /general/invoices/show/uuid/{uuid}` (detail endpoints)

Same summary fields **plus the full `snapshot`** (`InvoiceSnapshotResource`) and no `download_url` regression â€” detail keeps complete data.

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

### InvoiceSnapshotResource â€” the frozen order snapshot

Sections: `snapshot_version`, `snapshot_schema`, `order` `{id, order_number, status, payment_status, fulfillment_status}`, `customer` `{name}`, `billing_address`, `shipping_address`, `fulfillment` `{type, shipping_method, shipping_price, fast_shipping_fee, expected_delivery_at}`, `pickup_location`, `items[]` `{product_name, product_sku, attributes, quantity, unit_price, total_price, is_gift}`, `pricing_breakdown` `{subtotal, promotion_discount, coupon_discount, shipping_price, fast_shipping_fee, total, currency}`, `payment` `{method, gateway, paid_at}`, `metadata`, `audit` `{generated_by, generated_at}`.

### Collections

`AdminInvoiceCollection`, `CustomerInvoiceCollection`, `InvoiceCollection` all return `{ data: [...], links: { current_page, from, to, last_page, path, per_page, total, next_page_url, prev_page_url, last_page_url, first_page_url } }`.

> **Note:** `InvoiceResource` / `InvoiceCollection` currently have their `toArray()` bodies commented out. They are only referenced by `verify()` and are effectively broken (see the verify known issue). All live list/show/correct/cancel endpoints use the `Admin*`/`Customer*` resources.

---

## Reported Contradictions (source is authoritative)

1. **Verify is not public.** Older docs said `GET /verify/{uuid}` is public with `throttle:60,1`. Actual routes (both prefixes) sit inside `auth:sanctum` groups and add `throttle:5,1`.
2. **Dual registration resolved.** Older docs treated `/api/v1/invoices/verify/{uuid}` and `/api/v1/invoices/uuid/{uuid}` as stale customer paths that had moved under `/api/v1/general/...`. Both prefixes are currently registered and live: the admin package group (`Routes.php:391-403`) AND the customer general group (`routes/api.php:133-137`) resolve to the same controller actions. Customer-facing integrations should use `/api/v1/general/...`.
3. **`download_url` is broken in resources.** Both `AdminInvoiceResource` and `CustomerInvoiceResource` emit `download_url` = `/api/v1/general/invoices/{uuid}/download`, which is **not a registered route**. The real download route is `/api/v1/invoices/{uuid}/download`.
4. **`InvoiceResource` is disabled.** `InvoiceResource::toArray()` is fully commented out; `verify()` fails with `TypeError` (HTTP 500) on the authentic path.
5. **Download authorization is inline**, not a `permission:` middleware (route shows `[auth:sanctum, throttle:30,1]` only). Behavior matches the documented owner-OR-`view-invoice-download` rule.
6. **Route split (current).** Admin/staff routes live in the package: `packages/marvel/src/Rest/Routes.php` lines 391-403 (`index`, `{id}` show + mutations, `{uuid}/download`, plus `verify/{uuid}` and `uuid/{uuid}`), loaded under `api/v1`. Customer routes live in `routes/api.php` lines 133-137 inside the `v1/general` prefix (`my-invoices`, `verify/{uuid}`, `uuid/{uuid}`). Both groups bind `App\Http\Controllers\Api\InvoiceController`; permissions are enforced controller-constructor level, never route level.
