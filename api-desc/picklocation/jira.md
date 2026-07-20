# Jira - Pickup Location Feature

## Epic: Pickup Location Management

### Story Points Estimate: 8

## User Stories

### US-001: CRUD Pickup Locations (Admin)
**As** an admin
**I want** to create, read, update, and delete pickup locations
**So that** customers can collect orders at physical branches

**Acceptance Criteria:**
- List with search, active/inactive filter, pagination
- Order by display_order
- Create/edit with store_name, address, phone, email, coordinates, working_hours
- Soft delete (safe restoration)

### US-002: Public Pickup Location List (Checkout)
**As** a customer
**I want** to see available pickup locations during checkout
**So that** I can choose where to collect my order

**Acceptance Criteria:**
- Only active locations shown
- No authentication required
- Location snapshot saved on order at checkout

## Bug Tickets

| Ticket | Description | Priority | Severity |
|--------|-------------|----------|----------|
| BUG-001 | Pagination meta has duplicate `page`/`current_page` keys | Low | Low |
