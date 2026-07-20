# Dashboard Feature - API Investigation

## Feature Name

Admin Dashboard Analytics

## Description

The Dashboard feature provides 16 authenticated analytics endpoints for admin users, covering revenue, orders, products, customers, categories, coupons, carts, sales, finance, and payment reconciliation. All endpoints use a thin controller pattern delegating to `DashboardService` with 300-second caching.

## Architecture Overview

```
[Client (Admin)]
    |
    |--- GET /api/v1/general/dashboard/overview          (Auth: sanctum)
    |--- GET /api/v1/general/dashboard/revenue
    |--- GET /api/v1/general/dashboard/order-stats
    |--- GET /api/v1/general/dashboard/recent-orders
    |--- GET /api/v1/general/dashboard/top-products
    |--- ... (11 more endpoints)
    |
    v
[DashboardController]
    |--- Thin controller — all logic delegated
    |
    v
[DashboardService]
    |--- 16 methods with direct DB queries
    |--- Cache::remember (300s TTL)
    |--- No Repository layer
    |--- No FormRequest classes
    |--- No API Resource classes (raw arrays returned)
    |
    v
[Models: Order, Product, User, Transaction, Coupon, CouponUsage, Cart, Category]
```

## Key Endpoints (16 total)

| Method | URI | Cache TTL |
|--------|-----|-----------|
| GET | `/dashboard/overview` | 300s |
| GET | `/dashboard/revenue` | 300s |
| GET | `/dashboard/order-stats` | 300s |
| GET | `/dashboard/recent-orders` | 300s |
| GET | `/dashboard/top-products` | 300s |
| GET | `/dashboard/category-stats` | 300s |
| GET | `/dashboard/low-stock` | 300s |
| GET | `/dashboard/sales-analytics` | 300s |
| GET | `/dashboard/customer-analytics` | 300s |
| GET | `/dashboard/product-analytics` | 300s |
| GET | `/dashboard/order-analytics` | 300s |
| GET | `/dashboard/category-analytics` | 300s |
| GET | `/dashboard/coupon-analytics` | 300s |
| GET | `/dashboard/cart-analytics` | 300s |
| GET | `/dashboard/reconciliation` | No cache |
| GET | `/dashboard/finance-analytics` | 300s |

## Key Files

| Layer | Path |
|-------|------|
| Controller | `app/Http/Controllers/Api/General/DashboardController.php` |
| Service | `app/Services/Dashboard/DashboardService.php` |
| Model (Reconciliation) | `app/Models/PaymentReconciliationResult.php` |
| Enum (UserType) | `app/Enums/UserType.php` |
| Translation (EN) | `resources/lang/en/message.php` (18 keys) |
| Translation (AR) | `resources/lang/ar/message.php` (18 keys) |
| Seeder | `database/seeders/DashboardDataSeeder.php` |
| Test | `tests/Feature/DashboardTest.php` |

## Tech Stack

- **Laravel** with Eloquent ORM
- **Direct DB queries** — no Repository layer
- **Caching** — 300s TTL via `Cache::remember`
- **No FormRequests** — endpoints take optional query params only
- **No Policies** — only auth:sanctum middleware
- **No API Resources** — raw arrays returned
