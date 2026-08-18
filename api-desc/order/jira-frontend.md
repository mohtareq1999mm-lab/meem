# Jira - Order Feature (Frontend)

## Epic: Order Management UI — Customer App + Admin Dashboard

### Story Points Estimate: 8

---

## User Stories

### FE-US-001 (Admin): Orders Data Table
**As** an admin
**I want** a filterable, paginated orders table
**So that** I can browse and search orders

**Acceptance Criteria:**
- Fetches `GET /api/v1/orders` with query params
- Columns: Order #, Customer, Status, Date
- Filters: status dropdown, date range, text search
- Pagination controls
- Loading skeleton + error state

### FE-US-002 (Admin): Order Detail Page
**As** an admin
**I want** to see full order information
**So that** I can process and verify

**Acceptance Criteria:**
- Fetches `GET /api/v1/orders/{id}`
- Sections: customer info, items table, pricing breakdown, transactions
- Pickup location details if applicable
- Back to list navigation

### FE-US-003 (Customer): My Orders List
**As** a customer
**I want** to see my orders
**So that** I can track my purchases

**Acceptance Criteria:**
- Fetches `GET /api/v1/general/orders`
- Shows order number, status, totals, and invoice link (from `order_has_invoice` / `invoice_id`)

### FE-US-004 (Customer): Order Invoice View
**As** a customer
**I want** to view my order's invoice
**So that** I can review and verify it

**Acceptance Criteria:**
- Fetches `GET /api/v1/general/orders/invoice/{uuid}` using `invoice_id` from the list
- Shows invoice fields + snapshot + verification link
- Handles 403 (not owner) and 404

---

## Frontend Tasks

| ID | Description | h | Component |
|----|-------------|---|-----------|
| FE-T-001 | Create OrdersTable with filters (admin) | 6 | `OrdersTable.vue` |
| FE-T-002 | Create OrderDetailPage (admin) | 5 | `OrderDetailPage.vue` |
| FE-T-003 | Create API service | 1 | `services/orderApi.js` |
| FE-T-004 | Create filter components | 3 | `OrderFilters.vue` |
| FE-T-005 | Create customer My Orders page | 4 | `MyOrders.vue` |
| FE-T-006 | Create customer invoice view page | 4 | `OrderInvoiceView.vue` |

## API Routes

### Customer

| Method | Endpoint | Auth | Usage |
|--------|----------|------|-------|
| GET | `/api/v1/general/orders` | Sanctum | My orders |
| GET | `/api/v1/general/orders/invoice/{uuid}` | Sanctum + owner | Invoice view |

### Admin

| Method | Endpoint | Permission | Usage |
|--------|----------|-----------|-------|
| GET | `/api/v1/orders` | view-orders | Data table |
| GET | `/api/v1/orders/{id}` | view-order | Detail page |