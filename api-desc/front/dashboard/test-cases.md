# Test Cases - Dashboard Feature

## Current Coverage

**File:** `tests/Feature/DashboardTest.php` (860 lines, 28 tests)

| Category | Tests |
|----------|-------|
| Authentication | 1 |
| Original 7 endpoints | 7 |
| Sales Analytics | 2 |
| Customer Analytics | 1 |
| Product Analytics | 2 |
| Order Analytics | 2 |
| Category Analytics | 1 |
| Coupon Analytics | 1 |
| Cart Analytics | 2 |
| Finance Analytics | 2 |
| Edge Cases | 4 |
| Translation Resolution | 2 |
| Route Security | 2 |

## Known Bug: Tests Hit Wrong URLs

The test file uses `const PREFIX = '/api/v1'` instead of `/api/v1/general`. 24 of 28 tests will fail with 404.

| Test URL (used) | Actual Route |
|----------------|-------------|
| `/dashboard/sales` | `/dashboard/sales-analytics` |
| `/dashboard/customers` | `/dashboard/customer-analytics` |
| `/dashboard/products` | `/dashboard/product-analytics` |
| `/dashboard/orders` | `/dashboard/order-analytics` |
| `/dashboard/categories` | `/dashboard/category-analytics` |
| `/dashboard/coupons` | `/dashboard/coupon-analytics` |
| `/dashboard/cart` | `/dashboard/cart-analytics` |
| `/dashboard/finance` | `/dashboard/finance-analytics` |

## Recommended Tests

| # | Test | Description |
|---|------|-------------|
| FT-001 | Overview returns correct KPIs | All 7 fields present and typed |
| FT-002 | Revenue monthly breakdown | 12 months |
| FT-003 | Order stats for all periods | 4 period groups |
| FT-004 | All 16 endpoints return 200 | Smoke test |
| FT-005 | Cache invalidation on data change | stale data not served |
| FT-006 | Unauthenticated returns 401 | All endpoints |
