# Cart Module

## Overview

The Cart module manages user shopping carts with inventory reservation. Each authenticated user has one active cart. Items support two shipping methods (SCHEDULED / FAST), variant products, price snapshotting at reservation time, coupon application, and promotion-based gift items. Inventory is reserved on add and released on delete/expiry.

## Key Files

| Layer | File |
|-------|------|
| Controller | `packages/marvel/src/Http/Controllers/CartController.php` |
| Repository | `packages/marvel/src/Database/Repositories/CartRepository.php` |
| Inventory Service | `app/Services/General/CartInventoryService.php` |
| Cart Model | `packages/marvel/src/Database/Models/Cart.php` |
| CartItem Model | `packages/marvel/src/Database/Models/CartItem.php` |
| Cart Resource | `packages/marvel/src/Http/Resources/CartResource.php` |
| CartItem Resource | `packages/marvel/src/Http/Resources/CartItemResource.php` |
| Create Request | `packages/marvel/src/Http/Requests/CartCreateRequest.php` |
| Update Request | `packages/marvel/src/Http/Requests/CartUpdateRequest.php` |
| ShippingMethod Enum | `packages/marvel/src/Enums/ShippingMethod.php` |
| ProductPricing Service | `packages/marvel/src/Services/Pricing/ProductPricingService.php` |
| Promotion Service | `app/Services/General/PromotionService.php` |
| Coupon Calculator | `app/Services/Coupon/CouponCalculator.php` |
| Coupon Repository | `packages/marvel/src/Database/Repositories/CouponRepository.php` |
| CheckoutTotals DTO | `app/DTOs/CheckoutTotals.php` |
| ExpireCarts Command | `app/Console/Commands/ExpireCarts.php` |
| ExpireAbandonedCarts Command | `app/Console/Commands/ExpireAbandonedCarts.php` |
| Routes | `packages/marvel/src/Rest/Routes.php` (lines 160-168) |
| Cart Migration | `packages/marvel/database/migrations/..._create_carts_table.php` |
| CartItem Migration | `packages/marvel/database/migrations/..._create_cart_items_table.php` |
| FK Fix Migration | `packages/marvel/database/migrations/2026_07_17_000001_fix_cart_foreign_key_cascades.php` |
| Seeder | `database/seeders/CartSeeder.php` |
| Tests | `tests/Feature/CartApiTest.php` |
| Tests | `tests/Feature/CartExpirationTest.php` |

## Dependencies

- **Laravel Sanctum** — authentication
- **Prettus Repository** — repository pattern with caching
- **LockForUpdate (row-level locks)** — inventory concurrency
- **Rate Limiting** — `throttle:cart` (20 req/min per user)

## Routes

All cart routes are within a single authenticated group:

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| GET | `/api/v1/cart` | `auth:sanctum` | List user's carts (paginated) |
| POST | `/api/v1/cart` | `auth:sanctum` | Add item to cart |
| GET | `/api/v1/cart/{id}` | `auth:sanctum` | Show specific cart |
| POST | `/api/v1/cart/bulk-items` | `auth:sanctum` | Bulk add items |
| PUT | `/api/v1/cart/update-item` | `auth:sanctum` | Update item quantity |
| DELETE | `/api/v1/cart/delete-item/{itemId}` | `auth:sanctum` | Remove single item |
| DELETE | `/api/v1/cart/delete-items` | `auth:sanctum` | Clear entire cart |

**Rate Limit:** 20 requests per minute per user (configured in `RouteServiceProvider`).
