# Jira - Order Feature (Frontend)

## Epic: Admin Order Management UI

### Story Points Estimate: 8

---

## User Stories

### FE-US-001: Orders Data Table
**As** an admin
**I want** a filterable, paginated orders table
**So that** I can browse and search orders

**Acceptance Criteria:**
- Fetches `GET /api/v1/orders` with query params
- Columns: Order #, Customer, Status, Total, Date
- Filters: status dropdown, date range, text search
- Pagination controls
- Loading skeleton + error state

### FE-US-002: Order Detail Page
**As** an admin
**I want** to see full order information
**So that** I can process and verify

**Acceptance Criteria:**
- Fetches `GET /api/v1/orders/{id}`
- Sections: customer info, items table, pricing breakdown, transactions
- Pickup location details if applicable
- Back to list navigation

---

## Frontend Tasks

| ID | Description | h | Component |
|----|-------------|---|-----------|
| FE-T-001 | Create OrdersTable with filters | 6 | `OrdersTable.vue` |
| FE-T-002 | Create OrderDetailPage | 5 | `OrderDetailPage.vue` |
| FE-T-003 | Create API service | 1 | `services/orderApi.js` |
| FE-T-004 | Create filter components | 3 | `OrderFilters.vue` |

## API Routes

| Method | Endpoint | Permission | Usage |
|--------|----------|-----------|-------|
| GET | `/api/v1/orders` | VIEW_ORDERS | Data table |
| GET | `/api/v1/orders/{id}` | VIEW_ORDER | Detail page |
