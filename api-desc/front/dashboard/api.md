# API Documentation - Dashboard Feature

## Endpoints

All dashboard endpoints require `auth:sanctum` and are under prefix `/api/v1/general/dashboard`.

---

### 1. Overview

**GET** `/dashboard/overview`

**Response:**
```json
{
    "total_revenue": 800.00,
    "todays_revenue": 0.00,
    "total_refunds": 0.00,
    "total_orders": 150,
    "total_products": 45,
    "total_customers": 120,
    "new_customers": 5
}
```

---

### 2. Revenue

**GET** `/dashboard/revenue`

**Response:** `total_revenue`, `todays_revenue`, `monthly_breakdown` (12 months)

---

### 3. Order Stats

**GET** `/dashboard/order-stats`

**Response:** Status breakdown per period (today, weekly, monthly, yearly). Statuses: `pending`, `processing`, `completed`, `cancelled`, `refunded`, `failed`, `local_facility`, `out_for_delivery`.

---

### 4. Recent Orders

**GET** `/dashboard/recent-orders?limit=10`

**Query:** `limit` (default 10, max 50)

---

### 5. Top Products

**GET** `/dashboard/top-products?limit=10`

**Response:** Products with `sold_quantity > 0`, ordered by `sold_quantity DESC`.

---

### 6. Category Stats

**GET** `/dashboard/category-stats`

**Response:** `product_distribution` (top 15 categories by product count), `sales_distribution` (top 15 by sales).

---

### 7. Low Stock

**GET** `/dashboard/low-stock?limit=10`

**Response:** Products where `stock_quantity < 10` (hardcoded threshold).

---

### 8. Sales Analytics

**GET** `/dashboard/sales-analytics`

**Response:** `daily_revenue`, `revenue_comparison` (YoY, MoM, DoD), `average_order_value`, `revenue_by_payment_method`, `revenue_by_fulfillment_type`.

---

### 9. Customer Analytics

**GET** `/dashboard/customer-analytics`

**Response:** `new_vs_returning`, `monthly_growth`, `top_customers` (by orders + revenue), `customer_lifetime_value`, `active_customers`.

---

### 10. Product Analytics

**GET** `/dashboard/product-analytics`

**Response:** `best_selling`, `worst_selling`, `never_sold`, `out_of_stock`, `inventory_value`.

---

### 11. Order Analytics

**GET** `/dashboard/order-analytics`

**Response:** `timeline` (daily/weekly/monthly), `success_rate`, `refund_rate`.

---

### 12-16. Category, Coupon, Cart, Reconciliation, Finance Analytics

Similar structured analytics for respective domains. See `backend.md` for details.

---

## Business Rules

1. **Authentication:** All 16 endpoints require `auth:sanctum`. No permission checks beyond authentication.
2. **Caching:** 300s TTL on all endpoints except `/reconciliation`
3. **No Pagination:** All results are aggregated summaries
4. **Query Params:** Only `limit` is accepted (recent-orders, top-products, low-stock)
