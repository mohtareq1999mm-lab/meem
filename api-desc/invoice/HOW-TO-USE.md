# Invoice Endpoints â€” How To Use Guide

Practical usage guide for every invoice endpoint: **when** to call it, **why** it exists, exact **request**, and real **response**.

> **Route locations (source-verified):**
> - Admin group `/api/v1/invoices/*` â†’ `auth:sanctum` + `throttle:admin` + Spatie permission per action.
> - `verify/{uuid}` and `uuid/{uuid}` are registered under `/api/v1/general/invoices/*` (`auth:sanctum` + `throttle:authenticated`; verify adds `throttle:5,1`).
>
> All success payloads share the envelope: `{ "status": int, "message": string, "success": bool, "data": â€¦ }`.
>
> **Universal key â€” `view_url`:** every invoice payload (lists, detail, verify, mutations) now carries a ready-made **`view_url`** link so the frontend can open/render the invoice without constructing any URL:
> - Customer resources â†’ `/api/v1/general/orders/{order_id}/invoice`
> - Admin resource â†’ `/api/v1/invoices/{id}`

---

## 0. Which endpoint do I need?

| You want toâ€¦ | Use | Permission |
|---|---|---|
| Fill an admin invoices table (search/filter/sort) | **GET /invoices** | `view-invoices` |
| Open one invoice's full detail by its number-ID | **GET /invoices/{id}** | `view-invoice` |
| Open one invoice from a QR code / UUID link | **GET /general/invoices/uuid/{uuid}** | `view-invoice` |
| Get the actual PDF file | **GET /invoices/{uuid}/download** | owner OR `view-invoice-download` |
| PDF missing/failed/corrupted â†’ try again | **POST /invoices/{id}/regenerate** | `regenerate-invoice` |
| Wrong data already printed on an invoice | **POST /invoices/{id}/correct** | `correct-invoice` |
| Invoice issued by mistake â€” void it | **POST /invoices/{id}/cancel** | `cancel-invoice` |
| Customer owes MORE money after issuance | **POST /invoices/{id}/debit-note** | `issue-debit-note` |
| Check a paper/PDF invoice is genuine (anti-forgery) | **GET /general/invoices/verify/{uuid}** | any authenticated user |
| A customer asks "is this my invoice?" | **GET /general/invoices/verify/{uuid}** too | any authenticated user |

**Never:** edit an existing invoice's amounts in place, delete one, or mark payment through these endpoints â€” payment lives in the Order lifecycle; corrections/debit-notes are *new documents*.

---

## 1. GET /api/v1/invoices â€” List

**WHEN:** rendering the admin invoices screen, searching for an invoice to manage, building reports filtered by date/status.

**WHY this endpoint:** server-side pagination + whitelisted sorting keeps payloads small and prevents SQL injection via unknown sort fields (invalid `sort_by` silently falls back to `created_at`).

**Auth:** Sanctum Â· `view-invoices`

**Request:**
```
GET /api/v1/invoices?status=ready&search=INV-2026&from=2026-08-01&to=2026-08-31&sort_by=total&sort_direction=desc&limit=25&page=2
```
| Param | Notes |
|---|---|
| limit | default 15, hard max 100 |
| search | matches `invoice_number` OR related order number |
| status | any lifecycle status |
| order_id / user_id | exact filters |
| invoice_series / currency | exact filters |
| from / to | inclusive date range on `created_at` |
| sort_by / sort_direction | whitelist: created_at, total, status, invoice_number Â· asc/desc |

**Response 200:** `{ data: [AdminInvoiceResourceâ€¦], links: {current_page, per_page, total, last_page, â€¦} }`

---

## 2. GET /api/v1/invoices/{id} â€” Show

**WHEN:** admin opens the invoice detail page from the list row (you already have the numeric ID).

**WHY this endpoint:** single query returning everything the detail screen needs â€” financials, integrity hashes, generation health (`generation_attempts`, `last_generation_error`), QR block, and the immutable snapshot. Relations (`timeline`, credit/debit summaries) are intentionally omitted unless loaded.

**Auth:** Sanctum Â· `view-invoice` Â· `{id}` numeric-only (else routing 404).

**Request:** `GET /api/v1/invoices/12`

**Response 200 (trimmed):**
```json
{ "status":200, "message":"Data fetched successfully", "success":true,
  "data": {
    "id":12, "uuid":"550e8400-â€¦", "order_id":101,
    "invoice_number":"INV-2026-000012", "status":"ready",
    "subtotal":150.0, "shipping_price":10.0, "total_discount":5.0,
    "total":155.0, "amount_paid":155.0, "currency":"EGP",
    "generation_attempts":1, "last_generation_error":null,
    "is_correction":false,
    "verification_url":"https://â€¦/api/v1/general/invoices/verify/550e8400-â€¦",
    "view_url":"https://â€¦/api/v1/general/orders/101/invoice",
    "qr_content":{ "uuid":"550e8400-â€¦", "invoice_number":"INV-2026-000012",
                   "verification_hash":"c31bâ€¦", "issued_at":"2026-08-22T09:14:58+00:00",
                   "verification_url":"https://â€¦/verify/550e8400-â€¦" },
    "snapshot":{ "â€¦":"frozen order snapshot" }
} }
```

**Errors:** 404 missing id (`{"message":"Resource Not Found","status":false}`) Â· 401 Â· 403.

---

## 3. GET /api/v1/general/invoices/uuid/{uuid} â€” Show by UUID

**WHEN:** you only have the UUID (QR payload, customer link, cross-system reference) â€” not the numeric ID.

**WHY this endpoint:** same rich resource as Show, resolved by the public-safe identifier instead of the internal ID.

**Auth:** Sanctum Â· `view-invoice`

**Request:** `GET /api/v1/general/invoices/uuid/550e8400-e29b-41d4-a716-446655440000`

**Response 200:** identical shape to endpoint 2. **404** unknown uuid Â· **401** Â· **403**.

---

## 4. GET /api/v1/invoices/{uuid}/download â€” Download PDF

**WHEN:** the user presses â€œDownload PDFâ€. Call this first, then fetch the returned `url`.

**WHY this endpoint:** files live outside the DB on the `public` disk; the API returns a short-lived-style JSON pointer instead of streaming bytes, and rate-limits lookups (30/min). Authorization is privacy-first: a non-owner without `view-invoice-download` gets **404** â€” the response is identical to â€œinvoice not foundâ€, so attackers cannot enumerate invoices.

**Auth:** Sanctum Â· `throttle:30,1` Â· inline rule: invoice **owner** OR `view-invoice-download`.

**Request:** `GET /api/v1/invoices/550e8400-e29b-41d4-a716-446655440000/download`

**Response 200:**
```json
{ "status":200, "message":"Data fetched successfully", "success":true,
  "data":{ "url":"https://example.com/storage/invoices/INV-2026-000012.pdf",
           "invoice_number":"INV-2026-000012" } }
```

**Errors:**
| Code | Body | Meaning |
|---|---|---|
| 404 | `{message:"Not found"}` | unknown uuid **or** unauthorized (deliberately identical) |
| 404 | `{message:"PDF not yet generated", data:{status, pdf_generated_at}}` | job still running / failed â€” offer Regenerate |
| 401 | standard envelope | no token |

---

## 5. POST /api/v1/invoices/{id}/regenerate â€” Regenerate PDF

**WHEN:** `download` answered â€œPDF not yet generatedâ€, `status = failed`, the file was lost on disk, or a previous render was corrupted.

**WHY this endpoint:** PDF rendering is heavy (DomPDF) and runs asynchronously on queue `meem-medium` (3 tries). Regenerating flips the invoice into a transient state instead of blocking the HTTP call.

**Auth:** Sanctum Â· `regenerate-invoice` Â· allowed from `failed | ready | generated` only.

**Request:** `POST /api/v1/invoices/12/regenerate` â€” empty body.

**Response 200:**
```json
{ "status":200, "message":"Data fetched successfully", "success":true,
  "data":{ "invoice_id":12, "status":"pdf_generating" } }
```

**Afterwards:** poll endpoint 2 until `status = ready` (then Download) or `failed` (retry Regenerate). Each call increments `generation_attempts` and writes a `pdf_regenerated` timeline row.

**Errors:** 422 from any other status (cancelled/corrected/pdf_generatingâ€¦) Â· 404 missing id.

---

## 6. POST /api/v1/invoices/{id}/correct â€” Correct

**WHEN:** an issued invoice contains wrong data (total, customer info, addresses) that must stay historically visible.

**WHY this endpoint:** invoices are append-only financial records. Instead of mutating, the service creates a **brand-new invoice** (`is_correction=true`, linked via `correction_to_id`) with a fresh sequential number, re-hashed snapshot, and marks the original `corrected`. Audit trail stays intact; the original remains readable forever.

**Auth:** Sanctum Â· `correct-invoice` Â· original must be in `generated | ready | verified | downloaded | printed`.

**Request:**
```json
{
    "reason": "Wrong total charged",
    "overrides": {
        "total": 95.00,
        "amount_paid": 95.00,
        "shipping_price": 0,
        "customer": { "name": "Corrected Name", "email": "fixed@example.com" },
        "notes": "Support ticket #123"
    }
}
```
`reason` required (â‰¤500). Every override optional; unknown snapshot paths can also be set via dotted keys server-side.

**Response 200** â€” the **correction** invoice:
```json
{ "status":200, "message":"Invoice corrected successfully", "success":true,
  "data":{ "id":13, "invoice_number":"INV-2026-000013", "status":"generated",
           "total":95.0, "amount_paid":95.0, "is_correction":true,
           "correction_reason":"Wrong total charged",
           "corrected_at":"2026-08-22T10:00:00+00:00",
           "correction_to_id":12 } }
```

**Errors:** 422 business rule (`Invoice 12 cannot be corrected from status 'cancelled'`) Â· 422 validation (flat errors object) Â· 404 missing id (no internals leaked).

---

## 7. POST /api/v1/invoices/{id}/cancel â€” Cancel

**WHEN:** the whole invoice was issued by mistake (duplicate order, test entry) and must be voided â€” not corrected.

**WHY this endpoint:** cancellation is a terminal, reasoned state change: stamps `cancelled_at` + `cancellation_reason`, records a `cancelled` timeline event, and blocks further mutations. Repeat calls are rejected because `cancelled` cannot transition to itself.

**Auth:** Sanctum Â· `cancel-invoice` Â· allowed from `generated | ready | failed | corrected | verified | downloaded | printed`.

**Request:**
```json
{ "reason": "Duplicate invoice for refunded order" }
```

**Response 200:**
```json
{ "status":200, "message":"Invoice cancelled successfully", "success":true,
  "data":{ "id":12, "status":"cancelled",
           "cancelled_at":"2026-08-22T10:05:00+00:00",
           "cancellation_reason":"Duplicate invoice for refunded order" } }
```

**Errors:** 422 invalid status or repeated cancel Â· 422 missing reason Â· 404 missing id.

---

## 8. POST /api/v1/invoices/{id}/debit-note â€” Debit Note

**WHEN:** after issuance the customer owes **more** (extra shipping, penalty, under-charge discovered) and you must document the additional amount without touching the original totals.

**WHY this endpoint:** accounting separation â€” the debit note is its own numbered document (`DN-YYYY-######` series) referencing the invoice, keeping the original legally intact while the receivable increases. Created transactionally; multiple notes per invoice are allowed with sequential numbers.

**Auth:** Sanctum Â· `issue-debit-note` Â· invoice must be in `generated | ready | verified | downloaded | printed`.

**Request:**
```json
{ "amount": 25.50, "reason": "Additional shipping charge" }
```
`amount` required, min 0.01 Â· `reason` required â‰¤500.

**Response 201** (raw DebitNote model):
```json
{ "status":201, "message":"Debit note issued successfully", "success":true,
  "data":{ "id":3, "uuid":"a1b2c3d4-â€¦", "invoice_id":12,
           "debit_note_number":"DN-2026-000001", "debit_note_series":"DN",
           "sequence_number":1, "sequence_year":2026,
           "reason":"Additional shipping charge", "type":"correction",
           "amount":25.5, "currency":"EGP", "created_by":5,
           "notes":"Debit note for INV-2026-000012",
           "issued_at":"2026-08-22T10:10:00+00:00" } }
```

**Errors:** 422 invalid status (`Cannot issue debit note for invoice in status: archived`) Â· 422 validation Â· 404.

---

## 9. GET /api/v1/general/invoices/verify/{uuid} â€” Verify Authenticity

**WHEN:** someone presents a printed/PDF invoice (or scans its QR) and you must prove it was really issued by the system and was not altered.

**WHY this endpoint:** each invoice stores `snapshot_hash` + `verification_hash` (SHAâ€‘256 of the snapshot combined with the app secret). Verification recomputes and compares them with `hash_equals()` â€” constant-time, tamper-revealing. Throttled to **5/min** because verification is an integrity check, not a data feed.

**Auth:** Sanctum (any user) Â· `throttle:5,1`

**Request:** `GET /api/v1/general/invoices/verify/550e8400-e29b-41d4-a716-446655440000`

**Response 200 (authentic):**
```json
{ "status":200, "message":"Data fetched successfully", "success":true,
  "data":{
    "authentic":true,
    "invoice":{ "uuid":"550e8400-â€¦", "invoice_number":"INV-2026-000012",
                "status":"ready", "total":155.0, "currency":"EGP",
                "verify_count":1,
                "view_url":"https://â€¦/api/v1/general/orders/101/invoice" },
    "order":{ "id":101, "order_number":"ORD-00000101", "status":"completed",
              "payment_status":"paid", "fulfillment_status":"fulfilled" },
    "qr_content":"https://example.com/api/v1/general/invoices/verify/550e8400-â€¦" } }
```
Side effects per successful check: `verify_count++`, `last_verified_at=now()`, `verified_at` set once, timeline `verified`.

**Response 409 (tampered):**
```json
{ "status":409, "message":"Invoice verification failed", "success":false,
  "data":{ "authentic":false, "tampered":true } }
```

**Errors:** 404 unknown uuid (envelope) Â· 401 guest Â· 429 over 5/min.

---

## 10. GET /api/v1/general/invoices/uuid/{uuid} â€” Show by UUID

**WHEN:** an authenticated user follows a shared link / stored reference containing only the invoice UUID and needs the full detail view (not just authenticity).

**WHY this endpoint:** Verify answers â€œis it genuine?â€ with a compact payload + counter side effects; this endpoint answers â€œshow me everythingâ€ without touching verification counters â€” so UIs should prefer it for plain viewing.

**Auth:** Sanctum Â· `view-invoice`

**Request:** `GET /api/v1/general/invoices/uuid/550e8400-â€¦`

**Response 200:** identical to endpoint 2. **404** unknown uuid Â· **401** Â· **403**.

---

## Lifecycle & pairing cheat-sheet

```text
generated â”€â”€â–º pdf_generating â”€â”€â–º ready â”€â”€â–º downloaded/printed/verified/archived
    â”‚               â”‚                â”‚
    â”‚               â–¼                â–¼
    â”‚            failed â”€â”€(regenerate)â”€â”€â–º pdf_generating â€¦
    â”œâ”€â”€â–º corrected   (endpoint 6 creates a NEW invoice)
    â”œâ”€â”€â–º cancelled   (endpoint 7, terminal except archived)
    â””â”€â”€â–º debit note  (endpoint 8 â€” side document, invoice unchanged)
```

- **Correct vs Cancel vs Debit:** fix content â†’ Correct; kill document â†’ Cancel; add charges â†’ Debit Note.
- **Regenerate vs Download:** always Download first; Regenerate only on `failed` / missing-file / corrupted-PDF symptoms.
- **Verify vs Show-by-UUID:** proving genuineness â†’ Verify (counts, throttled); plain viewing â†’ Show by UUID.