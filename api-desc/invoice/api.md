# API Reference — Invoice

---

### GET /api/v1/invoices — List Invoices (Admin)

Paginated list of invoices with filtering, searching, and sorting.

**Authentication**: `auth:sanctum`, permission: `view-invoices`

**Query Parameters**:

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| limit | int | 15 | Items per page (max 100) |
| search | string | - | Search invoice_number or order_number (LIKE) |
| status | string | - | Filter by status (generated, ready, verified, cancelled, etc.) |
| order_id | int | - | Filter by order ID |
| user_id | int | - | Filter by user ID |
| invoice_series | string | - | Filter by series (INV) |
| currency | string | - | Filter by currency |
| from | date | - | Filter by created_at >= from |
| to | date | - | Filter by created_at <= to |
| sort_by | string | created_at | Sort field (created_at, total, status, invoice_number) |
| sort_direction | string | desc | Sort direction (asc, desc) |

**Response 200**:
```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "uuid": "550e8400-...",
        "order_id": 101,
        "invoice_number": "INV-2026-000001",
        "status": "ready",
        "subtotal": 100.0,
        "shipping_price": 10.0,
        "total_discount": 5.0,
        "total": 105.0,
        "amount_paid": 105.0,
        "currency": "EGP",
        "payment_method": "online",
        "payment_gateway": "myfatoorah",
        "pdf_generated_at": "2026-07-28T10:00:00Z",
        "generated_at": "2026-07-28T09:00:00Z",
        "is_correction": false,
        "verify_count": 0,
        "created_at": "2026-07-28T09:00:00Z",
        "verification_url": "http://example.com/api/v1/general/invoices/verify/550e8400-..."
      }
    ],
    "links": {
      "current_page": 1,
      "from": 1,
      "to": 15,
      "last_page": 1,
      "per_page": 15,
      "total": 1
    }
  }
}
```

---

### GET /api/v1/invoices/{id} — Show Invoice by ID (Admin)

**Authentication**: `auth:sanctum`, permission: `view-invoice`

Eager loads: `order.orderItems`, `transaction`, `user`

**Response 200**: Full InvoiceResource (see resource structure below)

**Response 404**:
```json
{ "status": 200, "message": "Not found", "success": false }
```

---

### GET /api/v1/invoices/uuid/{uuid} — Show Invoice by UUID (Admin)

**Authentication**: `auth:sanctum`, permission: `view-invoice`

Same response as show by ID.

---

### GET /api/v1/invoices/my-invoices — My Invoices (Customer)

**Authentication**: `auth:sanctum`

Returns paginated invoices for the authenticated user. No permission check (auto-scoped to user).

**Query Parameters**: `limit` (default 15, max 100)

Eager loads: `order`

**Response 200**: InvoiceCollection with user-scoped invoices.

---

### GET /api/v1/invoices/verify/{uuid} — Verify Invoice (Public)

**Authentication**: None. Throttle: 60 requests per minute.

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
    "invoice": { ...InvoiceResource... },
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

**Response 409 (Tampered)**:
```json
{
  "status": 409,
  "message": "Invoice verification failed",
  "success": false,
  "data": { "authentic": false, "tampered": true }
}
```

**Response 404**:
```json
{ "status": 404, "message": "Not found", "success": false }
```

**Business Rules**:
- Verification compares `verification_hash` (HMAC-SHA256 of `snapshot_hash` + app key) against computed value
- `verify_count` is incremented on each verification
- `last_verified_at` is updated; `verified_at` is set only on first verification
- Timeline event `verified` is recorded

---

### GET /api/v1/invoices/{uuid}/download — Download PDF

**Authentication**: `auth:sanctum`. Access: invoice owner OR user with `view-invoice` permission.

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| uuid | string | Invoice UUID |

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

**Response 404 (Not found or unauthorized)**:
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

**Business Rules**:
- Non-owner users must have `view-invoice` permission (returns 404 if not authorized — privacy)
- `downloaded_at` is set only on first download
- Timeline event `downloaded` is recorded

---

### POST /api/v1/invoices/{id}/regenerate — Regenerate PDF

**Authentication**: `auth:sanctum`, permission: `regenerate-invoice`

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| id | int | Invoice ID |

**Response 200**:
```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": {
    "invoice_id": 1,
    "status": "pdf_generating"
  }
}
```

**Response 422** (invalid status):
```json
{ "status": 422, "message": "...", "success": false }
```

**Business Rules**:
- Can only regenerate if status is `failed`, `ready`, or `generated` (not cancelled/archived/corrected)
- Sets status to `pdf_generating`, increments `generation_attempts`, clears `last_generation_error`
- Dispatches `GenerateInvoicePdfJob` to queue

---

### POST /api/v1/invoices/{id}/correct — Correct Invoice

**Authentication**: `auth:sanctum`, permission: `correct-invoice`

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| id | int | Invoice ID |

**Request Body**:

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

**Response 200**:
```json
{
  "status": 200,
  "message": "Invoice corrected successfully",
  "success": true,
  "data": { ...InvoiceResource of correction... }
}
```

**Response 422** (invalid status):
```json
{
  "status": 422,
  "message": "Invoice 1 cannot be corrected from status 'cancelled'",
  "success": false
}
```

**Business Rules**:
- Creates a NEW invoice (correction) with a new invoice number
- Marks the original invoice as `corrected` status
- Only allowed from statuses: `generated`, `ready`, `verified`, `downloaded`, `printed`
- Overrides are applied via `data_set()` on the snapshot
- New invoice is auto-generated with `is_correction = true`
- Both original and correction get timeline events
- `GenerateInvoicePdfJob` is dispatched for the correction

---

### POST /api/v1/invoices/{id}/cancel — Cancel Invoice

**Authentication**: `auth:sanctum`, permission: `cancel-invoice`

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| id | int | Invoice ID |

**Request Body**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| reason | string | required | Cancellation reason (max 500) |

**Response 200**:
```json
{
  "status": 200,
  "message": "Invoice cancelled successfully",
  "success": true,
  "data": { ...InvoiceResource... }
}
```

**Response 422** (invalid status):
```json
{
  "status": 422,
  "message": "Invoice 1 cannot be cancelled from status 'archived'",
  "success": false
}
```

**Business Rules**:
- Sets status to `cancelled`, `cancelled_at`, and `cancellation_reason`
- Only allowed from: `generated`, `ready`, `failed`, `corrected`, `verified`, `downloaded`, `printed`
- Uses `lockForUpdate` inside transaction
- Timeline event `cancelled` recorded

---

### POST /api/v1/invoices/{id}/debit-note — Issue Debit Note

**Authentication**: `auth:sanctum`, permission: `issue-debit-note`

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| id | int | Invoice ID |

**Request Body**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| amount | numeric | required | Debit amount (min 0.01) |
| reason | string | required | Debit reason (max 500) |

**Response 201**:
```json
{
  "status": 201,
  "message": "Debit note issued successfully",
  "success": true,
  "data": { ...DebitNote data... }
}
```

**Response 422** (invalid status):
```json
{
  "status": 422,
  "message": "Cannot issue debit note for invoice in status: archived",
  "success": false
}
```

**Business Rules**:
- Only allowed from statuses: `generated`, `ready`, `verified`, `downloaded`, `printed`
- Debit note gets auto-numbered via `InvoiceNumberService` with `DN` series prefix
- Transactional creation
