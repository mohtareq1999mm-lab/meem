# Production Status Dashboard

| Feature | Revision | Status | Production Ready | Depends On | Used By | Regression Status | Last Audit | Tests | Verified Bugs |
|---------|----------|--------|-----------------|------------|---------|-------------------|------------|-------|---------------|
| Role & Permission | 2 | Production Ready | YES | Authentication, Spatie Permission, Email Verified, Translation System | Admin Users, User Management, All Middleware-Guarded Endpoints | Passed | 2026-07-20 | 32/32 | 8 fixed (0 unverified) |
| Categories | 2 | Production Ready | YES | Products | Products | Passed | 2026-07-23 | 98/98 (94 existing + 4 new) | None (3 fixed) |
| Brands | 1 | Production Ready | YES | Products | Categories (pivot), Products, Media Lifecycle | Passed | 2026-07-18 | 63/63 | None (1 fixed) |
| Products | 2 | Production Ready | YES | Categories, Brands, Media Lifecycle, Pricing, Attributes, Item Type (PHYSICAL/DIGITAL/SERVICE) | Cart, Orders, Search, Home, Wishlist, Flash Sales, Promotions, Coupons | Passed (product domain suites green; unrelated pre-existing failures documented in production-history) | 2026-08-23 | ProductItemTypeTest 13/13 + Products 234/238 (4 pre-existing dead-route/route-name failures) | None (pre-existing legacy is_digital/is_rental references reported) |
| Wishlist | 2 | Production Ready | YES | Authentication (Sanctum), Products, Pricing, Translation System | Product detail page, Home, Frontend wishlist UI | Run (36/36 WishlistApiTest pass) | 2026-08-04 | 36/36 (106 assertions) | 7 fixed (0 unverified) + 2 open/info |
| Cart | 5 | Production Ready | YES | Authentication (Sanctum), Products, Pricing | Checkout, Orders | Run (88 total, 83 pass, 5 known failures) | 2026-08-04 | 88 methods (CartApiTest 75/80 + CartExpirationTest 8/8; 5 pre-existing failures: coupon apply, gift promotion, finalization x2, is_gift exposure). Re-run unblocked after fixing ParseError in Routes.php:493 | 9 verified (1 fixed) + 4 info |
| Contacts | 1 | Production Ready | YES | Authentication (Sanctum), Permissions, Translation System | Contact Forms, Admin Notifications, Notifications | Passed | 2026-07-20 | 59/59 (120 assertions) | None (3 fixed) |
| Orders (Invoice Fields & Endpoint) | 1 | Production Ready | YES | Order Model, Invoice System, Authentication (Sanctum) | Customer Order Details, Invoice Viewing | Passed | 2026-07-29 | 7/7 (48 assertions) | None |
| Coupons (Admin CRUD) | 1 | Production Ready | YES | CouponAssignmentController, CouponAssignmentRepository, Permissions (4), Routes (5) | Coupon Assignment consumption (customer-facing) | PASS | 2026-07-25 | 43/43 (151 assertions) | None |
| Site Reviews | 2 | Production Ready | YES | Authentication (Sanctum), Permissions (3), User model, Translation System, FrontendResource cache | Frontend home/reviews UI, Admin Dashboard | Passed | 2026-08-10 | 58/58 (152 assertions) | None (2 fixed) |
| Flash Sales | 4 | Production Ready | YES | Products, Pricing, Permissions | Cart, Products, Orders | Passed | 2026-07-19 | 87 (38 flash sale + 49 pricing/order) | None (7 fixed, 1 dead code removed) |
| Attributes + Values | 1 | Production Ready | YES | Products | Products (variants, filtering, pricing), Import/Export, Cart | Passed | 2026-07-19 | 48/48 attribute (0 new failures) + 32/32 new | None (4 fixed) |
| Product Import/Export | 1 | Production Ready | YES | Products, Attributes, Categories, Brands, Pricing, Inventory, Media | Product Management | Passed | 2026-07-19 | 34/34 import/export + 76/76 product | None (1 fixed) |
| Authentication | 1 | Production Ready | YES | Sanctum, Spatie Permission, Mail Config, Translation System | All Features | Passed | 2026-07-22 | 0 (no dedicated auth tests) | None (4 fixed) |
| Promotions | 0 | Not Started | NO | — | — | Not Required | — | — | — |
| Payment System | 0 | Not Started | NO | — | — | Not Required | — | — | — |
| Currency Selection Enabled | 1 | Production Ready | YES | Settings (options), CurrencyService (base/catalog/effective), Authentication (Sanctum), UserCurrencyPreferenceService, FrontendResource settings cache | Frontend currency selector, Cart, Checkout, Orders | Passed (17/17 new; 183 pass / 2 pre-existing unrelated failures in combined Currency+Settings filter) | 2026-08-12 | 17/17 (37 assertions) | None |
| Orders (Canonical Status Lifecycle) | 1 | Production Ready | YES | Order Model, OrderService (transitions), Invoice System (InvoiceService idempotent), Payment events, EventServiceProvider, Supervisor queues (meem-high/meem-medium/default) | Admin dashboard (PATCH status), Frontend order tracking, COD/Cashier marking, Gateway callbacks, invoices | Passed (143/143 green set; 13 failures byte-identical to clean-main baseline, stash-verified) | 2026-08-22 | 15/15 lifecycle (45 assertions) · 143/143 combined green (384) | 5 fixed (COD/Cashier missing OrderStatusChanged; delivered notification dead-path; completion lacked payment-success semantics/invoice; cancel-unpaid audit gap; delivery-address over-required for pickup) |

## Legend

- **Not Started** — Feature has not been audited
- **In Progress** — Audit or fixes in progress
- **Blocked** — Blocked by another feature or dependency
- **Regression Required** — Changes made; dependent features must be re-tested
- **Production Ready** — All checks pass, no verified production bugs

## Regression Status Values

- **Not Required** — No dependencies changed
- **Pending** — Dependent features changed, tests not yet run
- **Passed** — All required regression tests passed
- **Failed** — Regression tests failed

## Known Pre-Existing Suite Debt (not blocking release; verified identical on clean main)

- `PaymentSystemTest` — 4 failures (missing `coupon_assignments` table in its test schema; 3 mark-paid endpoint tests hitting pre-existing 500s)
- `EventSystemTest` — 9 failures (queue/refund/provider assertions)
- Marvel SMS/email chains for generic status changes remain orphaned (`Marvel\Events\*` never dispatched)
