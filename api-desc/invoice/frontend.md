# Invoice Module — Frontend Integration Guide

Pure endpoint reference. Customer endpoints first, then the admin group. Every endpoint lists: authentication, request body/query parameters, and real response structures.

---

# CUSTOMER ENDPOINTS

---

## 1. GET /api/v1/general/invoices/my-invoices — My Invoices

**Auth:** Sanctum (no permission — auto-scoped to authenticated user)

| Parameter | Type | Default | Notes |
|-----------|------|---------|-------|
| limit | int | 15 | Max 100 |

**Request:** `GET /api/v1/general/invoices/my-invoices?limit=15`

**Response 200:** paginated customer items.

Customer item fields (lightweight list — **no snapshot**): `uuid`, `invoice_number`, `status`, `subtotal`, `shipping_price`, `total_discount`, `total`, `currency`, `payment_method`, `payment_gateway`, `generated_at`, `pdf_generated_at`, `verification_url` (when uuid), **`view_url`** (when order_id — ready-made link to open this invoice), `download_url` (when uuid AND pdf_path — points to the registered route `/api/v1/invoices/{uuid}/download`).

```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": {
        "data": [
            {
                "uuid": "550e8400-e29b-41d4-a716-446655440000",
                "invoice_number": "INV-2026-000012",
                "status": "ready",
                "subtotal": 150.0,
                "shipping_price": 10.0,
                "total_discount": 5.0,
                "total": 155.0,
                "currency": "EGP",
                "payment_method": "online",
                "payment_gateway": "myfatoorah",
                "generated_at": "2026-08-22T09:14:58+00:00",
                "pdf_generated_at": "2026-08-22T09:15:00+00:00",
                "verification_url": "https://example.com/api/v1/general/invoices/verify/550e8400-…",
                "view_url": "https://example.com/api/v1/general/invoices/show/uuid/550e8400-�",
                "download_url": "…"
            }
        ],
        "links": { "current_page": 1, "per_page": 15, "total": 4 }
    }
}
```

> **Do not use the item's `download_url`.** It emits `/api/v1/general/invoices/{uuid}/download`, which is not a registered route. Build downloads as `/api/v1/invoices/{uuid}/download` (endpoint 7).

**Errors:** `401` guest.

---

## 2. GET /api/v1/general/orders/{orderId}/invoice — View One Invoice (Customer, Canonical)

**Auth:** Sanctum · `{orderId}` numeric-only · Ownership scoped in query

**Request:** path parameter = the **Order ID** from the customer order list. No invoice uuid extraction needed.

**Resolution:**
- Pending order (no invoice yet) → **404** `{ status: 404, "message": "Not found", "success": false }`
- Foreign / missing order → identical **404** envelope (no existence leak)
- Found → `200` with the full customer item below (always including `snapshot`) — payload identical to the legacy uuid route; returns the correction when one exists

```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": {
        "uuid": "550e8400-…",
        "invoice_number": "INV-2026-000012",
        "status": "ready",
        "subtotal": 150.0,
        "shipping_price": 10.0,
        "total_discount": 5.0,
        "total": 155.0,
        "currency": "EGP",
        "payment_method": "online",
        "payment_gateway": "myfatoorah",
        "generated_at": "2026-08-22T09:14:58+00:00",
        "pdf_generated_at": "2026-08-22T09:15:00+00:00",
        "verification_url": "https://example.com/api/v1/general/invoices/verify/550e8400-…",
        "download_url": "…",
        "snapshot": {
            "snapshot_version": "2.1.0",
            "order": { "id": 101, "order_number": "ORD-00000101", "status": "completed" },
            "customer": { "name": "Test Customer" },
            "items": [ { "product_name": "Widget", "quantity": 2, "unit_price": 75.0, "total_price": 150.0 } ],
            "pricing_breakdown": { "subtotal": 150.0, "coupon_discount": 0.0, "promotion_discount": 5.0, "shipping_price": 10.0, "total": 155.0, "currency": "EGP" },
            "payment": { "method": "online", "gateway": "myfatoorah", "paid_at": "2026-08-22T09:10:00+00:00" },
            "audit": { "generated_by": "system", "generated_at": "2026-08-22T09:14:58+00:00" }
        }
    }
}
```

### ~~Legacy variant~~ — REMOVED

`GET /api/v1/general/orders/invoice/{uuid}` and its `OrderController::invoice()` method were **deleted (2026-08-22)**. Requests fail routing with 404. Use the canonical Order-ID endpoint above; locate documents by Order ID only.

**Errors:** `401` guest · `403` non-owner · `404` unknown uuid.

---

## 3. GET /api/v1/general/invoices/verify/{uuid} — Verify Invoice

**Auth:** Sanctum · `throttle:5,1`

**Request:** path parameter `uuid`.

**Response 200 (authentic):**
```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": {
        "authentic": true,
        "invoice": {
            "uuid": "550e8400-…",
            "invoice_number": "INV-2026-000012",
            "status": "ready",
            "total": 155.0,
            "currency": "EGP",
            "verify_count": 1
        },
        "order": {
            "id": 101,
            "order_number": "ORD-00000101",
            "status": "completed",
            "payment_status": "paid",
            "fulfillment_status": "fulfilled"
        },
        "qr_content": "https://example.com/api/v1/general/invoices/verify/550e8400-…"
    }
}
```
> `data.invoice` now returns full invoice data (`InvoiceResource` restored 2026-08-22). Consume `authentic`, `invoice`, `order`, `qr_content`.

**Response 409 (tampered):**
```json
{
    "status": 409,
    "message": "Invoice verification failed",
    "success": false,
    "data": { "authentic": false, "tampered": true }
}
```

**Response 404:** `{ "status": 404, "message": "Not found", "success": false }`

---

# ADMIN ENDPOINTS

---

## 4. GET /api/v1/invoices — List Invoices

**Auth:** Sanctum · Permission: `view-invoices`

| Parameter | Type | Default | Notes |
|-----------|------|---------|-------|
| limit | int | 15 | Max 100 |
| search | string | — | Matches `invoice_number` or order number |
| status | string | — | e.g. `ready` |
| order_id | int | — | |
| user_id | int | — | |
| invoice_series | string | — | |
| currency | string | — | |
| from / to | date | — | Filters `created_at` |
| sort_by | enum | created_at | created_at, total, status, invoice_number |
| sort_direction | asc/desc | desc | |

**Request:** `GET /api/v1/invoices?status=ready&limit=20&sort_by=total`

**Response 200:**
```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": {
        "data": [
            {
                "id": 12,
                "uuid": "550e8400-e29b-41d4-a716-446655440000",
                "order_id": 101,
                "invoice_number": "INV-2026-000012",
                "status": "ready",
                "subtotal": 150.0,
                "shipping_price": 10.0,
                "coupon_discount": 0.0,
                "promotion_discount": 5.0,
                "total_discount": 5.0,
                "total": 155.0,
                "amount_paid": 155.0,
                "currency": "EGP",
                "payment_method": "online",
                "payment_gateway": "myfatoorah",
                "snapshot_hash": "9f2a…",
                "verification_hash": "c31b…",
                "pdf_generated_at": "2026-08-22T09:15:00+00:00",
                "generated_at": "2026-08-22T09:14:58+00:00",
                "generation_attempts": 1,
                "last_generation_error": null,
                "is_correction": false,
                "correction_reason": null,
                "corrected_at": null,
                "cancelled_at": null,
                "cancellation_reason": null,
                "verified_at": null,
                "downloaded_at": null,
                "printed_at": null,
                "archived_at": null,
                "last_verified_at": null,
                "verify_count": 0,
                "created_at": "2026-08-22T09:14:58+00:00"
            }
        ],
        "links": {
            "current_page": 1,
            "from": 1,
            "to": 20,
            "last_page": 3,
            "path": "https://example.com/api/v1/invoices",
            "per_page": 20,
            "total": 55,
            "next_page_url": "https://example.com/api/v1/invoices?page=2",
            "prev_page_url": null,
            "last_page_url": "https://example.com/api/v1/invoices?page=3",
            "first_page_url": "https://example.com/api/v1/invoices?page=1"
        }
    }
}
```

---

## 5. GET /api/v1/invoices/{id} — Show Invoice

**Auth:** Sanctum · Permission: `view-invoice` · `{id}` numeric-only (non-numeric → route 404)

**Request:** path parameter `id`.

**Response 200:** full admin resource.

Admin item fields: `id`, `uuid`, `order_id`, `invoice_number`, `status`, `subtotal`, `shipping_price`, `coupon_discount`, `promotion_discount`, `total_discount`, `total`, `amount_paid`, `currency`, `payment_method`, `payment_gateway`, `snapshot_hash`, `verification_hash`, `pdf_generated_at`, `generated_at`, `generation_attempts`, `last_generation_error`, `is_correction`, `correction_reason`, `corrected_at`, `cancelled_at`, `cancellation_reason`, `verified_at`, `downloaded_at`, `printed_at`, `archived_at`, `last_verified_at`, `verify_count`, `created_at`, plus conditionals: `verification_url`, **`view_url`** (when order_id — canonical viewer link), `qr_content {uuid, invoice_number, verification_hash, issued_at, verification_url}`, `download_url` (when pdf exists), `snapshot`.

```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": {
        "id": 12,
        "uuid": "550e8400-…",
        "order_id": 101,
        "invoice_number": "INV-2026-000012",
        "status": "ready",
        "subtotal": 150.0,
        "shipping_price": 10.0,
        "coupon_discount": 0.0,
        "promotion_discount": 5.0,
        "total_discount": 5.0,
        "total": 155.0,
        "amount_paid": 155.0,
        "currency": "EGP",
        "payment_method": "online",
        "payment_gateway": "myfatoorah",
        "generation_attempts": 1,
        "is_correction": false,
        "verify_count": 0,
        "verification_url": "https://example.com/api/v1/general/invoices/verify/550e8400-…",
        "qr_content": {
            "uuid": "550e8400-…",
            "invoice_number": "INV-2026-000012",
            "verification_hash": "c31b…",
            "issued_at": "2026-08-22T09:14:58+00:00",
            "verification_url": "https://example.com/api/v1/general/invoices/verify/550e8400-…"
        },
        "download_url": "https://example.com/api/v1/general/invoices/550e8400-…/download",
        "snapshot": { "…": "see endpoint 2" }
    }
}
```

> Same `download_url` warning as section 1 — use endpoint 7 for downloads.

**Errors:** `404` missing id (`{"message":"Resource Not Found","status":false}`) · `401` guest · `403` no permission.

---

## 6. GET /api/v1/general/invoices/uuid/{uuid} — Show Invoice by UUID

**Auth:** Sanctum · Permission: `view-invoice`

**Request:** path parameter `uuid`.

**Response 200:** identical shape to endpoint 5.
**Errors:** `404` unknown uuid · `401` · `403`.

---

## 7. GET /api/v1/invoices/{uuid}/download — Download PDF

**Auth:** Sanctum · `throttle:30,1` · Inline rule: **owner OR `view-invoice-download`** (an authenticated customer may download their own invoice here)

**Request:** path parameter `uuid` (UUID format enforced).

**Response 200:**
```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": {
        "url": "https://example.com/storage/invoices/INV-2026-000012.pdf",
        "invoice_number": "INV-2026-000012"
    }
}
```

**Error responses:**
```json
// 404 — unauthorized OR unknown uuid (identical body, no existence leak)
{ "status": 404, "message": "Not found", "success": false }

// 404 — PDF not generated yet
{
    "status": 404,
    "message": "PDF not yet generated",
    "success": false,
    "data": { "status": "pdf_generating", "pdf_generated_at": null }
}

// 401
{ "message": "Unauthenticated", "status": false }
```

---

## 8. POST /api/v1/invoices/{id}/regenerate — Regenerate PDF

**Auth:** Sanctum · Permission: `regenerate-invoice` · `{id}` numeric-only

**Request Body:** none (empty).

**Response 200:**
```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": { "invoice_id": 12, "status": "pdf_generating" }
}
```

**Response 422** (status not in `failed` / `ready` / `generated`):
```json
{ "status": 422, "message": "ERROR_ADDING_ITEMS_TO_ORDER", "success": false }
```

**Response 404:** `{"message":"Resource Not Found","status":false}` — id does not exist.

---

## 9. POST /api/v1/invoices/{id}/correct — Correct Invoice

**Auth:** Sanctum · Permission: `correct-invoice` · `{id}` numeric-only

**Request Body:**
```json
{
    "reason": "Wrong total charged",
    "overrides": {
        "total": 95.00,
        "amount_paid": 95.00,
        "shipping_price": 0,
        "customer": {
            "name": "Corrected Name",
            "email": "corrected@example.com",
            "phone": "+201000000000"
        },
        "billing_address": { "city": "Cairo" },
        "shipping_address": { "city": "Giza" },
        "notes": "Adjusted after support ticket #123"
    }
}
```

Validation: `reason` required, max 500 · all `overrides.*` optional (`total`/`amount_paid`/`shipping_price` numeric ≥ 0 · `customer.email` valid email · addresses arrays · `notes` string).

**Response 200** — resource of the **new correction invoice**:
```json
{
    "status": 200,
    "message": "Invoice corrected successfully",
    "success": true,
    "data": {
        "id": 13,
        "uuid": "7c9e6679-742f-45de-…",
        "order_id": 101,
        "invoice_number": "INV-2026-000013",
        "status": "generated",
        "total": 95.0,
        "amount_paid": 95.0,
        "currency": "EGP",
        "is_correction": true,
        "correction_reason": "Wrong total charged",
        "corrected_at": "2026-08-22T10:00:00+00:00",
        "generation_attempts": 0,
        "verification_url": "https://example.com/api/v1/general/invoices/verify/7c9e6679-…",
        "view_url": "https://example.com/api/v1/general/invoices/show/uuid/550e8400-�",
        "snapshot": { "…": "cloned snapshot with overrides applied" }
    }
}
```

**Response 422** (original status not in `generated/ready/verified/downloaded/printed`):
```json
{ "status": 422, "message": "Invoice 12 cannot be corrected from status 'cancelled'", "success": false }
```

**Response 404:** `{"message":"Resource Not Found","status":false}` — id does not exist (no internals leaked).

**Response 422 (validation)** — flat errors object:
```json
{ "reason": ["The reason field is required."], "overrides.total": ["The overrides.total must be at least 0."] }
```

---

## 10. POST /api/v1/invoices/{id}/cancel — Cancel Invoice

**Auth:** Sanctum · Permission: `cancel-invoice` · `{id}` numeric-only

**Request Body:**
```json
{ "reason": "Order refunded" }
```

Validation: `reason` required, string, max 500.

**Response 200** — refreshed resource with terminal state:
```json
{
    "status": 200,
    "message": "Invoice cancelled successfully",
    "success": true,
    "data": {
        "id": 12,
        "uuid": "550e8400-…",
        "invoice_number": "INV-2026-000012",
        "status": "cancelled",
        "cancelled_at": "2026-08-22T10:05:00+00:00",
        "cancellation_reason": "Order refunded",
        "total": 155.0,
        "currency": "EGP",
        "is_correction": false
    }
}
```

**Response 422** (status not in `generated/ready/failed/corrected/verified/downloaded/printed`):
```json
{ "status": 422, "message": "Invoice 12 cannot be cancelled from status 'cancelled'", "success": false }
```

**Response 404:** `{"message":"Resource Not Found","status":false}`

**Response 422 (validation):** `{ "reason": ["The reason field is required."] }`

---

## 11. POST /api/v1/invoices/{id}/debit-note — Issue Debit Note

**Auth:** Sanctum · Permission: `issue-debit-note` · `{id}` numeric-only

**Request Body:**
```json
{ "amount": 25.50, "reason": "Additional shipping charge" }
```

Validation: `amount` required, numeric, min 0.01 · `reason` required, max 500.

**Response 201** — raw DebitNote attributes (no resource wrapper):
```json
{
    "status": 201,
    "message": "Debit note issued successfully",
    "success": true,
    "data": {
        "id": 3,
        "uuid": "a1b2c3d4-…",
        "invoice_id": 12,
        "debit_note_number": "DN-2026-000001",
        "debit_note_series": "DN",
        "sequence_number": 1,
        "sequence_year": 2026,
        "reason": "Additional shipping charge",
        "type": "correction",
        "amount": 25.5,
        "currency": "EGP",
        "created_by": 5,
        "line_items": [ "…" ],
        "notes": "Debit note for INV-2026-000012",
        "issued_at": "2026-08-22T10:10:00+00:00",
        "updated_at": "2026-08-22T10:10:00+00:00",
        "created_at": "2026-08-22T10:10:00+00:00"
    }
}
```

**Response 422** (status not in `generated/ready/verified/downloaded/printed`):
```json
{ "status": 422, "message": "Cannot issue debit note for invoice in status: archived", "success": false }
```

**Response 404:** handler 404 envelope (id does not exist).

**Response 422 (validation):**
```json
{ "amount": ["The amount must be at least 0.01."], "reason": ["The reason field is required."] }
```

---

## Status Values (as returned in every invoice payload)

`pending` · `generating` · `generated` · `pdf_generating` · `ready` · `failed` · `verified` · `downloaded` · `printed` · `corrected` · `cancelled` · `archived`