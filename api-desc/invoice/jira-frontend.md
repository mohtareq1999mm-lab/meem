# Invoice Module — Frontend Jira Tasks

---

## Task 1: Admin Invoice Listing Page

**Priority:** High
**Component:** Frontend — Admin Invoices Page
**Story Points:** 8

**Description:** Build the admin invoice management page with a data table supporting filtering, searching, sorting, and pagination.

**API Endpoint:** `GET /api/v1/invoices`

**Acceptance Criteria:**
- [ ] Table columns: invoice number, order number, customer, status badge, total, currency, dates
- [ ] Pagination controls (page size selector, prev/next)
- [ ] Filter dropdowns: status, payment method, currency
- [ ] Date range picker for from/to filtering
- [ ] Search: invoice number or order number
- [ ] Sortable columns: created_at, total, status, invoice_number
- [ ] Click row → navigate to invoice detail
- [ ] **Loading state:** Skeleton table rows
- [ ] **Empty state:** "No invoices found"
- [ ] **Error state:** Error with "Retry" button

---

## Task 2: Admin Invoice Detail Page

**Priority:** High
**Component:** Frontend — Admin Invoice Detail
**Story Points:** 8

**Description:** Build the invoice detail page showing all invoice data, snapshot, timeline, and action buttons.

**API Endpoints:**
- `GET /api/v1/invoices/{id}`
- `GET /api/v1/invoices/{uuid}/download`

**Acceptance Criteria:**
- [ ] Display invoice header: number, status, dates
- [ ] Financial summary: subtotal, discounts, shipping, total, amount paid
- [ ] Order information section linked to order detail
- [ ] Customer information
- [ ] Billing and shipping addresses
- [ ] Items table with quantities, prices, discounts
- [ ] Payment information (method, gateway, transaction)
- [ ] Timeline/audit log of status changes
- [ ] PDF download button (if PDF is ready)
- [ ] Action buttons: regenerate (if failed), correct, cancel, issue debit note
- [ ] Debit/Credit notes summary
- [ ] Correction chain (link to original and corrections)
- [ ] Verification QR code display
- [ ] **Loading state:** Full page skeleton
- [ ] **Error state (404):** "Invoice not found"

---

## Task 3: Admin Invoice Correction Form

**Priority:** High
**Component:** Frontend — Correction Modal
**Story Points:** 5

**Description:** Build a modal form for correcting an invoice with overridable fields.

**API Endpoint:** `POST /api/v1/invoices/{id}/correct`

**Acceptance Criteria:**
- [ ] Reason textarea (required)
- [ ] Overridable fields: total, amount paid, shipping price, customer name/email/phone
- [ ] Address overrides for billing and shipping
- [ ] Notes override
- [ ] Validation errors displayed per field
- [ ] Confirmation dialog before submitting
- [ ] Success → redirect to correction invoice detail
- [ ] **Loading state:** Submit spinner
- [ ] **Error state:** Display API error

---

## Task 4: Admin Invoice Cancellation

**Priority:** High
**Component:** Frontend — Cancel Modal
**Story Points:** 3

**Description:** Build a cancellation confirmation modal.

**API Endpoint:** `POST /api/v1/invoices/{id}/cancel`

**Acceptance Criteria:**
- [ ] Reason textarea (required, max 500)
- [ ] Warning about irreversibility
- [ ] Confirmation checkbox
- [ ] Success → invoice status updated with grey badge
- [ ] **Loading state:** Submit spinner
- [ ] **Error state:** 422 for invalid status

---

## Task 5: Admin Debit Note Issuance

**Priority:** Medium
**Component:** Frontend — Debit Note Modal
**Story Points:** 3

**Description:** Build a form for issuing debit notes against an invoice.

**API Endpoint:** `POST /api/v1/invoices/{id}/debit-note`

**Acceptance Criteria:**
- [ ] Amount input (required, min 0.01)
- [ ] Reason textarea (required)
- [ ] Shows debit note summary on invoice detail after issuance
- [ ] **Loading/error states**

---

## Task 6: Public Invoice Verification Page

**Priority:** Medium
**Component:** Frontend — Public Verification
**Story Points:** 5

**Description:** Build a public invoice verification page accessed via QR code or URL.

**API Endpoint:** `GET /api/v1/invoices/verify/{uuid}`

**Acceptance Criteria:**
- [ ] Input field for UUID or verification URL
- [ ] Verify button
- [ ] **Authentic state:** Green checkmark, invoice summary, order info, QR content display
- [ ] **Tampered state:** Red warning, "Invoice may have been tampered"
- [ ] **Not found state:** "Invoice not found"
- [ ] QR code generation from verification URL
- [ ] Print-friendly layout

---

## Task 7: Customer My Invoices Page

**Priority:** High
**Component:** Frontend — Customer Dashboard
**Story Points:** 5

**Description:** Build a customer-facing invoice list and detail page.

**API Endpoints:**
- `GET /api/v1/invoices/my-invoices`
- `GET /api/v1/invoices/{uuid}/download`

**Acceptance Criteria:**
- [ ] List of invoices with number, status, total, date
- [ ] Click to expand/detail
- [ ] Download PDF button
- [ ] Verify button (opens verification page)
- [ ] **Loading/empty/error states**

---

## Task 8: Status Badge Component

**Priority:** Medium
**Component:** Frontend — Shared Component
**Story Points:** 2

**Color mapping:**
| Status | Color |
|--------|-------|
| pending | Gray |
| generating | Blue |
| generated | Blue |
| pdf_generating | Yellow |
| ready | Green |
| failed | Red |
| verified | Indigo |
| downloaded | Cyan |
| printed | Purple |
| corrected | Orange |
| cancelled | Dark Gray |
| archived | Dark Gray |

---

## Task 9: PDF Download with Progress Indicator

**Priority:** Medium
**Component:** Frontend — PDF Download
**Story Points:** 3

**Description:** Handle the asynchronous PDF generation flow.

**Acceptance Criteria:**
- [ ] After create/regenerate, show "Generating PDF..." progress
- [ ] Poll invoice status until `ready` or `failed`
- [ ] On `ready`: enable download button
- [ ] On `failed`: show error with "Retry" button
- [ ] Direct download triggers browser download
- [ ] **Loading/error states** for each phase
