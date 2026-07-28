# Invoice Module — Frontend Integration Guide

## Endpoints

---

### 1. GET /api/v1/invoices — List Invoices (Admin)

**Authentication:** Required (Sanctum), Permission: `view-invoices`

**Query Parameters:** `limit`, `search`, `status`, `order_id`, `user_id`, `invoice_series`, `currency`, `from`, `to`, `sort_by`, `sort_direction`

**Response:** Paginated `InvoiceCollection` with full `InvoiceResource` items.

---

### 2. GET /api/v1/invoices/my-invoices — My Invoices (Customer)

**Authentication:** Required (Sanctum)

**Query Parameters:** `limit` (default 15, max 100)

**Response:** Paginated `InvoiceCollection` scoped to authenticated user.

---

### 3. GET /api/v1/invoices/{uuid}/download — Download PDF

**Authentication:** Required (Sanctum). Access: owner OR `view-invoice` permission.

**Response:**
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

**Error (no PDF):** `{ status: 404, message: "PDF not yet generated", data: { status, pdf_generated_at } }`

---

### 4. GET /api/v1/invoices/{id} — Show Invoice (Admin)

**Authentication:** Required (Sanctum), Permission: `view-invoice`

**Response:** Full `InvoiceResource` including snapshot, timeline, debit/credit notes.

---

### 5. GET /api/v1/invoices/verify/{uuid} — Verify Invoice (Public)

**Authentication:** None. Throttle: 60 req/min.

**Response (authentic):**
```json
{
  "status": 200,
  "success": true,
  "data": {
    "authentic": true,
    "invoice": { ...InvoiceResource... },
    "order": { "id": 101, "order_number": "ORD-001", "status": "completed", "payment_status": "paid", "fulfillment_status": "fulfilled" },
    "qr_content": "http://example.com/api/v1/general/invoices/verify/..."
  }
}
```

**Response (tampered):** 409 with `{ authentic: false, tampered: true }`

---

### 6. POST /api/v1/invoices/{id}/regenerate — Regenerate PDF (Admin)

**Authentication:** Required (Sanctum), Permission: `regenerate-invoice`

**Request:** Empty body (no payload needed)

**Response:** `{ invoice_id, status: "pdf_generating" }`

---

### 7. POST /api/v1/invoices/{id}/correct — Correct Invoice (Admin)

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

**Response:** Full `InvoiceResource` of the correction invoice.

---

### 8. POST /api/v1/invoices/{id}/cancel — Cancel Invoice (Admin)

**Authentication:** Required (Sanctum), Permission: `cancel-invoice`

**Request:**
```json
{ "reason": "Order refunded" }
```

**Response:** Full `InvoiceResource` with cancelled status.

---

### 9. POST /api/v1/invoices/{id}/debit-note — Issue Debit Note (Admin)

**Authentication:** Required (Sanctum), Permission: `issue-debit-note`

**Request:**
```json
{ "amount": 25.00, "reason": "Additional shipping" }
```

**Response (201):** Debit note data.

---

## Frontend Usage

```javascript
export const invoiceApi = {
  list(params)                    // GET /api/v1/invoices
  myInvoices(params)             // GET /api/v1/invoices/my-invoices
  get(id)                        // GET /api/v1/invoices/{id}
  getByUuid(uuid)                // GET /api/v1/invoices/uuid/{uuid}
  verify(uuid)                   // GET /api/v1/invoices/verify/{uuid}
  download(uuid)                 // GET /api/v1/invoices/{uuid}/download
  regenerate(id)                 // POST /api/v1/invoices/{id}/regenerate
  correct(id, data)              // POST /api/v1/invoices/{id}/correct
  cancel(id, reason)             // POST /api/v1/invoices/{id}/cancel
  issueDebitNote(id, data)       // POST /api/v1/invoices/{id}/debit-note
}
```

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

## Key Considerations

1. **Verify endpoint is public** — Can be used for QR code scanning without authentication
2. **Download authorization** — Customers can only download their own invoices; admins need `view-invoice` permission
3. **PDF generation is asynchronous** — After create/regenerate, poll status until `ready`
4. **Corrections create new invoices** — The original invoice is marked as `corrected` but not deleted
5. **Snapshot is immutable** — The invoice data is a frozen copy at time of generation; corrections create separate records
6. **Verification** — Uses HMAC; if the invoice data was tampered with after generation, verification will fail
7. **No delete** — Invoices are immutable financial records; they cannot be deleted, only cancelled or archived
