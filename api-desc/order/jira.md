# Jira - Order Feature

## Epic: Order Management (Read) — Customer + Admin

### Story Points Estimate: 8

## User Stories

### US-001 (Admin): View Order List
**As** an admin
**I want** to view a paginated list of all orders with filters
**So that** I can manage and review orders effectively

**Acceptance Criteria:**
- Paginated list with configurable limit (15–100)
- Filter by status, user, email, date range
- Search by name/email/phone
- Filter by product, promotion, shipping method
- List returns minimal items (no items/transactions)

### US-002 (Admin): View Order Detail
**As** an admin
**I want** to view full order details including items, transactions, and pricing
**So that** I can process and verify orders

**Acceptance Criteria:**
- Load order by ID or tracking number
- Display customer info, items, pricing breakdown
- Display transactions and payment status
- Show pick-up location if fulfillment is pickup

### US-003 (Customer): My Orders List
**As** a customer
**I want** to view my own orders
**So that** I can track my purchases

**Acceptance Criteria:**
- Scoped to authenticated user only
- Show order number, status, totals, invoice indicator
- Filter by status

### US-004 (Customer): View Order Invoice
**As** a customer
**I want** to view the invoice of my order
**So that** I can review invoice details and verify it

**Acceptance Criteria:**
- Owner-only access (403 otherwise)
- Returns invoice JSON + snapshot + verification URL

## Bug Tickets

| Ticket | Description | Priority | Severity |
|--------|-------------|----------|----------|
| BUG-001 | No explicit orderBy on list query | Low | Low |
| BUG-002 | Nested promotion_name filter uses subquery without index | Medium | Medium |
| BUG-003 | CustomerInvoiceResource.download_url points to non-existent route | High | Medium |
| BUG-004 | Customer order list never emits invoice_summary (only order_has_invoice/invoice_id) | Info | Low |