# Frontend - Dashboard Feature

## Status

**No dedicated frontend Vue/React components** found. Frontend is a separate SPA.

## Consumption Patterns

All 16 endpoints are consumed by an admin SPA:

```javascript
// services/dashboardApi.js
export const dashboardApi = {
  overview()                    // GET /api/v1/general/dashboard/overview
  revenue()                     // GET /api/v1/general/dashboard/revenue
  orderStats()                  // GET /api/v1/general/dashboard/order-stats
  recentOrders(limit)           // GET /api/v1/general/dashboard/recent-orders
  topProducts(limit)            // GET /api/v1/general/dashboard/top-products
  categoryStats()               // GET /api/v1/general/dashboard/category-stats
  lowStock(limit)               // GET /api/v1/general/dashboard/low-stock
  salesAnalytics()              // GET /api/v1/general/dashboard/sales-analytics
  customerAnalytics()           // GET /api/v1/general/dashboard/customer-analytics
  productAnalytics()            // GET /api/v1/general/dashboard/product-analytics
  orderAnalytics()              // GET /api/v1/general/dashboard/order-analytics
  categoryAnalytics()           // GET /api/v1/general/dashboard/category-analytics
  couponAnalytics()             // GET /api/v1/general/dashboard/coupon-analytics
  cartAnalytics()               // GET /api/v1/general/dashboard/cart-analytics
  reconciliation()              // GET /api/v1/general/dashboard/reconciliation
  financeAnalytics()            // GET /api/v1/general/dashboard/finance-analytics
}
```

## What a Frontend Implementation Would Need

```
DashboardPage.vue
  Fetches: GET /api/v1/general/dashboard/overview
  Renders: KPI cards (revenue, orders, customers, products)

SalesAnalyticsChart.vue
  Fetches: GET /api/v1/general/dashboard/sales-analytics
  Renders: Revenue charts, comparison widgets

OrderAnalytics.vue
  Fetches: GET /api/v1/general/dashboard/order-analytics
  Renders: Timeline chart, success/refund rates

RecentOrdersTable.vue
  Fetches: GET /api/v1/general/dashboard/recent-orders?limit=10
  Renders: Table with user, pickup location, totals
```
