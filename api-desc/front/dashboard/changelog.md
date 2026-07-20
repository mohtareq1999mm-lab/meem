# Changelog - Dashboard Feature

## [Unreleased]

### Added
- 16 admin dashboard analytics endpoints
- `DashboardController` with thin controller pattern
- `DashboardService` with direct Eloquent queries
- 300-second caching on 15 of 16 endpoints
- Overview, Revenue, Order Stats, Recent Orders, Top Products, Category Stats, Low Stock
- Sales, Customer, Product, Order, Category, Coupon, Cart, Finance Analytics
- Payment Reconciliation summary
- `DashboardDataSeeder` with 150 customers, 500 orders, 200 carts
- 18 translation keys (EN + AR)
- 28 test methods in DashboardTest.php

### Known Issues
- Tests use incorrect URL prefix (missing `/general`) — 24/28 tests fail
- Order stats hardcodes 5 statuses to 0
- No pagination on any endpoint
- No API Resource classes — raw arrays returned
