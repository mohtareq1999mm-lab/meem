# Jira - Dashboard Feature

## Epic: Admin Dashboard Analytics

### Story Points Estimate: 13

## User Stories

### US-001: View Dashboard Overview
**As** an admin
**I want** to see key metrics (revenue, orders, customers, products) on a dashboard
**So that** I can quickly assess business health

### US-002: View Sales Analytics
**As** an admin
**I want** to see sales trends, comparisons, and breakdowns by payment method
**So that** I can analyze revenue performance

### US-003: View Customer Analytics
**As** an admin
**I want** to see customer growth, top customers, and lifetime value
**So that** I can understand my customer base

### US-004: View Inventory Analytics
**As** an admin
**I want** to see best/worst selling products, out-of-stock items, and inventory value
**So that** I can manage inventory effectively

### US-005: View Financial Reconciliation
**As** an admin
**I want** to see payment reconciliation status and mismatches
**So that** I can ensure financial accuracy

## Bug Tickets

| Ticket | Description | Priority | Severity |
|--------|-------------|----------|----------|
| BUG-001 | Tests use wrong URL prefix (missing `/general`) | Critical | High |
| BUG-002 | Order stats hardcodes 5 statuses to 0 | Medium | Medium |
| BUG-003 | RecentOrders missing orderBy clause | Low | Low |
| BUG-004 | Magic number thresholds throughout | Low | Low |
