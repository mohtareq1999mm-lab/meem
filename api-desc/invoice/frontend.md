# Invoice Module — Frontend Integration Guide

## View vs Download vs Preview (read first)

**Three distinct operations.** Do not conflate them:

| Action | What you get | Auth needed | Endpoint |
|--------|--------------|-------------|----------|
| **View invoice data** | JSON fields + immutable snapshot | Customer: owner (via order) / Admin: `view-invoice` | `GET /api/v1/general/orders/invoice/{uuid}` or `GET /api/v1/invoices/{id}` |
| **Verify authenticity** | `{ authentic, order, qr_content }` | `auth:sanctum` | `GET /api/v1/general/invoices/verify/{uuid}` |
| **Download PDF** | JSON `{ url, invoice_number }` → then GET the `url` | Owner OR `view-invoice-download` | `GET /api/v1/invoices/{uuid}/download` |
| **PDF preview** | **NOT CURRENTLY PROVIDED** | — | — |

> **PDF preview does not exist.** There is no `/preview` route. The download endpoint returns a JSON body containing a storage URL (never the PDF bytes). If the UI needs a preview, render the returned `url` inside an `<iframe>`/`<embed>` — this still triggers the download authorization rule (owner OR `view-invoice-download`).

**Ownership rule:** `GET /orders/invoice/{uuid}` returns 403 for a non-owner. `GET /invoices/{uuid}/download` returns **404** for a non-owner without `view-invoice-download` (privacy — do not reveal the invoice exists).

---

## Endpoints

### 1. GET /api/v1/invoices — List Invoices (Admin)

**Authentication:** Required (Sanctum), Permission: `view-invoices`

**Query Parameters:** `limit` (default 15, max 100), `search`, `status`, `order_id`, `user_id`, `invoice_series`, `currency`, `from`, `to`, `sort_by` (created_at/total/status/invoice_number), `sort_direction` (asc/desc)

**Response:** Paginated `AdminInvoiceCollection` → `{ data: [AdminInvoiceResource], links: {...} }`.

---

### 2. GET /api/v1/general/invoices/my-invoices — My Invoices (Customer)

**Authentication:** Required (Sanctum). No permission check (auto-scoped to user).

**Query Parameters:** `limit` (default 15, max 100)

**Response:** Paginated `CustomerInvoiceCollection`.

**Customer resource fields:** `uuid`, `invoice_number`, `status`, `subtotal`, `shipping_price`, `total_discount`, `total`, `currency`, `payment_method`, `payment_gateway`, `generated_at`, `pdf_generated_at`, `verification_url`, `download_url` (only when `uuid` AND `pdf_path`), `snapshot` (only when data present).

---

### 3. GET /api/v1/general/orders/invoice/{uuid} — View One Invoice (Customer)

**Authentication:** Required (Sanctum). Owner-only → **403** for non-owner, **404** unknown uuid, **401** guest.

**Response:** `CustomerInvoiceResource` (same shape as a my-invoices item, including `snapshot`).

> This is the recommended **view invoice data** endpoint for the storefront.

---

### 4. GET /api/v1/invoices/{uuid}/download — Download PDF

**Authentication:** Required (Sanctum), `throttle:30,1`. No middleware permission — authorization is inline.

**Access:** Owner **OR** user with `view-invoice-download`. (`view-invoice` alone does NOT authorize download.)

**Authorization matrix (source + tests `InvoiceDownloadPermissionTest`, 18/18 green):**

| User | Download? |
|------|-----------|
| Owner (no permission) | **200** |
| Owner + `view-invoice` | **200** |
| Non-owner + `view-invoice-download` | **200** |
| Non-owner + `view-invoice` only | **404** |
| Non-owner, no permission | **404** |
| Guest | **401** |
| Super admin (has `view-invoice-download`) | **200** |

**Response 200:**
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
Then fetch `url` to obtain the PDF file. Requires the `storage` symlink.

**Errors:**
- `404 { message: "Not found" }` — unknown uuid OR unauthorized (same shape, do not leak).
- `404 { message: "PDF not yet generated", data: { status, pdf_generated_at } }` — invoice has no PDF yet.
- `401` — unauthenticated.

**Side effects:** `downloaded_at` set only on **first** download; timeline `downloaded` event on every download. Status is not changed by download.

---

### 5. GET /api/v1/invoices/{id} — Show Invoice (Admin)

**Authentication:** Required (Sanctum), Permission: `view-invoice`

**Response:** `AdminInvoiceResource` — full field set incl. `snapshot`, `timeline`, `credit_notes_summary`, `debit_notes_summary`.

---

### 6. GET /api/v1/general/invoices/uuid/{uuid} — Show Invoice by UUID (Admin)

**Authentication:** Required (Sanctum), Permission: `view-invoice`

**Response:** `AdminInvoiceResource`.

---

### 7. GET /api/v1/general/invoices/verify/{uuid} — Verify Invoice

**Authentication:** Required (Sanctum) + `throttle:5,1`. *(Older docs said public/60-per-min — source currently requires auth; see api.md contradictions.)*

**Response (authentic):**
```json
{
  "status": 200,
  "success": true,
  "data": {
    "authentic": true,
    "invoice": {},
    "order": { "id": 101, "order_number": "ORD-001", "status": "completed", "payment_status": "paid", "fulfillment_status": "fulfilled" },
    "qr_content": "http://example.com/api/v1/general/invoices/verify/550e8400-..."
  }
}
```
> **Known issue:** `invoice` currently serializes as `{}` because `InvoiceResource::toArray()` is commented out — the endpoint actually throws (500) on this path. Do not consume `data.invoice`.

**Response (tampered):** 409 with `{ authentic: false, tampered: true }`

**Response 404:** `{ status: 404, message: "Not found", success: false }`

---

### 8. POST /api/v1/invoices/{id}/regenerate — Regenerate PDF (Admin)

**Authentication:** Required (Sanctum), Permission: `regenerate-invoice`

**Request:** Empty body.

**Response:** `{ invoice_id, status: "pdf_generating" }` (200). 422 if current status is not `failed`/`ready`/`generated`.

---

### 9. POST /api/v1/invoices/{id}/correct — Correct Invoice (Admin)

**Authentication:** Required (Sanctum), Permission: `correct-invoice`

**Request:**
```json
{
  "reason": "Wrong customer name",
  "overrides": {
    "total": 95.00,
    "customer": { "name": "Corrected Name" }
  }
}
```

**Response:** 200 with `AdminInvoiceResource` of the correction. 422 on invalid status (`generated/ready/verified/downloaded/printed` allowed only).

---

### 10. POST /api/v1/invoices/{id}/cancel — Cancel Invoice (Admin)

**Authentication:** Required (Sanctum), Permission: `cancel-invoice`

**Request:** `{ "reason": "Order refunded" }` (required, max 500)

**Response:** 200 with `AdminInvoiceResource` (status `cancelled`). 422 on invalid status.

---

### 11. POST /api/v1/invoices/{id}/debit-note — Issue Debit Note (Admin)

**Authentication:** Required (Sanctum), Permission: `issue-debit-note`

**Request:** `{ "amount": 25.00, "reason": "Additional shipping" }` (amount min 0.01)

**Response:** 201 with raw `DebitNote` attributes. 422 on invalid status.

---

## Frontend Usage (Quick Reference)

```javascript
export const invoiceApi = {
  // Admin
  list(params)                    // GET /api/v1/invoices                  (view-invoices)
  get(id)                         // GET /api/v1/invoices/{id}             (view-invoice)
  getByUuid(uuid)                 // GET /api/v1/general/invoices/uuid/{uuid}  (view-invoice)
  download(uuid)                  // GET /api/v1/invoices/{uuid}/download  (owner OR view-invoice-download)
  regenerate(id)                  // POST /api/v1/invoices/{id}/regenerate (regenerate-invoice)
  correct(id, data)               // POST /api/v1/invoices/{id}/correct    (correct-invoice)
  cancel(id, reason)              // POST /api/v1/invoices/{id}/cancel     (cancel-invoice)
  issueDebitNote(id, data)        // POST /api/v1/invoices/{id}/debit-note (issue-debit-note)

  // Customer
  myInvoices(params)              // GET /api/v1/general/invoices/my-invoices        (auth)
  orderInvoice(uuid)              // GET /api/v1/general/orders/invoice/{uuid}       (owner)
  verify(uuid)                    // GET /api/v1/general/invoices/verify/{uuid}      (auth + throttle 5/min)
}
```

> **Note:** the resource-provided `download_url` field points to `/api/v1/general/invoices/{uuid}/download` which is **not a route**. Always build the download URL as `/api/v1/invoices/{uuid}/download`.

---

## Decision Tree

```
Does the user need to SEE invoice contents on screen?
  → Use VIEW: GET /orders/invoice/{uuid}  (customer, owner)
             GET /invoices/{id}           (admin, view-invoice)
             → JSON + snapshot, no PDF involved.

Does the user need the actual PDF FILE?
  ├─ Is the user the invoice owner?
  │    ├─ Yes → GET /invoices/{uuid}/download → 200 { url, invoice_number }
  │    └─ No  → does the user hold view-invoice-download?
  │              ├─ Yes → 200
  │              └─ No  → 404 (view-invoice alone is NOT enough)
  └─ Is a preview needed (no save)?
       → NOT SUPPORTED by backend. Render the download `url` in an <iframe>.
         Same authorization rule applies (owner OR view-invoice-download).

Does the user need to prove authenticity (QR)?
  → GET /general/invoices/verify/{uuid} → authentic true/false (auth required, 5/min).
    NOTE: data.invoice currently broken (500) — only consume authentic/order/qr_content.
```

---

## Status Display

| Status | Display Label | Color |
|--------|---------------|-------|
| pending | Pending | Gray |
| generating | Generating | Blue |
| generated | Generated | Blue |
| pdf_generating | PDF Generating | Yellow |
| ready | Ready | Green |
| failed | Failed | Red |
| verified | Verified | Indigo |
| downloaded | Downloaded | Cyan |
| printed | Printed | Purple |
| corrected | Corrected | Orange |
| cancelled | Cancelled | Gray/Dark |
| archived | Archived | Dark Gray |

---

## Key Considerations

1. **PDF preview is NOT provided** — build any preview from the download URL; it still requires owner/`view-invoice-download`.
2. **Download authorization** — customers download only their own invoices; admins need `view-invoice-download` (`view-invoice` alone is NOT sufficient). Unauthorized attempts return 404 (not 403).
3. **PDF generation is asynchronous** — after create/regenerate, poll status until `ready`; only then will `pdf_path`/`download_url` be present.
4. **Corrections create new invoices** — the original is marked `corrected`, not deleted.
5. **Snapshot is immutable** — invoice `data` is a frozen copy at generation time.
6. **Verification** — HMAC-based; tampered invoices return 409. Currently requires auth + is throttled 5/min.
7. **No delete** — invoices are immutable financial records; only cancelled or archived.
8. **Verify endpoint caveats** — requires `auth:sanctum`, and `data.invoice` is currently broken (500) due to a disabled resource.
