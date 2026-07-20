# Jira - Order Feature

## Epic: Admin Order Management (Read)

### Story Points Estimate: 8

## User Stories

### US-001: View Order List
**As** an admin
**I want** to view a paginated list of all orders with filters
**So that** I can manage and review orders effectively

**Acceptance Criteria:**
- Paginated list with configurable limit (15–100)
- Filter by status, user, email, date range
- Search by name/email/phone
- Filter by product, promotion, shipping method

### US-002: View Order Detail
**As** an admin
**I want** to view full order details including items, transactions, and pricing
**So that** I can process and verify orders

**Acceptance Criteria:**
- Load order by ID or tracking number
- Display customer info, items, pricing breakdown
- Display transactions and payment status
- Show pick-up location if fulfillment is pickup

## Bug Tickets

| Ticket | Description | Priority | Severity |
|--------|-------------|----------|----------|
| BUG-001 | No explicit orderBy on list query | Low | Low |
| BUG-002 | Nested promotion_name filter uses subquery without index | Medium | Medium |
