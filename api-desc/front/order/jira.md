# Jira - Order Feature

## Epic: Order Management

### Story Points Estimate: 21

## User Stories

### US-001: View My Orders (Customer)
**As** a customer
**I want** to view my order history
**So that** I can track my purchases

**Acceptance Criteria:**
- `GET /api/v1/general/orders` returns authenticated user's orders
- Paginated with order number, status, total, date
- Search by order number

### US-002: Checkout (Customer)
**As** a customer
**I want** to place an order from my cart
**So that** I can purchase products

**Acceptance Criteria:**
- `POST /api/v1/general/checkout` with customer info
- Supports COD, online payment, pay-at-cashier
- Validates cart, inventory, governorate
- Price snapshot preserves current prices

### US-003: Manage Orders (Admin)
**As** an admin
**I want** to view and manage all orders
**So that** I can process fulfillment

**Acceptance Criteria:**
- `GET /api/v1/orders` with filters
- `PUT /api/v1/orders/{id}` status transitions
- `POST /checkout/cod/{id}/mark-paid` for COD payments

### US-004: Export & Invoices (Admin)
**As** an admin
**I want** to export orders and download invoices
**So that** I can manage accounting

### US-005: Order Events & Notifications
**As** a customer
**I want** to receive notifications when my order status changes
**So that** I stay informed

## Bug Tickets

| Ticket | Description | Priority | Severity | Status |
|--------|-------------|----------|----------|--------|
| BUG-000 | Status filter ignored on `/api/v1/general/orders` — all statuses returned regardless of query param | High | High | **FIXED** |
| BUG-001 | Dual model system: legacy vs modern columns | Medium | Medium | Open |
| BUG-002 | Commented apiResource routes in Routes.php | Low | Low | Open |
| BUG-003 | No base orders migration found | Low | Low | Open |
| BUG-004 | Duplicate route definitions for checkout endpoints | Low | Low | Open |
| BUG-005 | Missing EN/AR translation files (only DE exists) | Medium | Medium | Open |
