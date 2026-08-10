# Feature Dependency Graph

---

## Authentication

**Purpose:**
Handle user registration, login, logout, password reset, email verification, and profile retrieval.

**Dependency Confidence:**
All dependencies verified from source code.

**Depends On:**
- Sanctum — token-based API authentication (Verified)
- Spatie Laravel Permission — role/permission checks in auth response (Verified)
- Mail Config — `log` driver (dev), SMTP/Mailgun/SES (prod) (Verified)
- Translation System — response messages via `__()` constants (Verified)

**Used By:**
- Every feature that requires authentication (Verified)
- Password reset flow used by all users (Verified)

**Regression Required When Changed:**
- Authentication (all auth endpoints)
- All feature tests (comprehensive regression — every endpoint uses auth)

**Blocking Dependencies:**
None

**Current Status:**
Production Ready

**Revision History:**
- Rev 1 (2026-07-22): Fixed SMTP mail driver causing password reset 500 errors; fixed sendUserOtp exception handling; fixed verifyForgetPasswordToken empty response; added missing EN translation keys

---

## Role & Permission

**Purpose:**
Manage roles, permissions, and user-role mappings for role-based access control (RBAC).

**Dependency Confidence:**
All dependencies verified from source code.

**Depends On:**
- Authentication — Sanctum (Verified)
- Spatie Laravel Permission package (Verified)
- Email Verified middleware — route group middleware: `email.verified` (Verified)
- Translation system — Spatie HasTranslations on Role model (Verified)

**Used By:**
- Admin Users — assigns roles via `User::syncRoles()` (Verified)
- User Management — role-based access checks (Verified)
- All features using `role:` or `permission:` middleware (Verified)

**Regression Required When Changed:**
- RoleAndPermissionTest
- UserControllerTest (admin user management portion)
- All feature tests (comprehensive regression)

**Blocking Dependencies:**
None

**Current Status:**
Production Ready

**Revision History:**
- Rev 1 (2026-07-17): Initial production audit — fixed 2 bugs (missing translations, showRole 500)
- Rev 2 (2026-07-20): Full production hardening — fixed 8 bugs (duplicate routes, display_name false, missing fields, delete cascade, login missing fields)

---

## Admin Users

**Purpose:**
Manage admin users — create, update, delete, ban, activate, restore.

**Dependency Confidence:**
Dependencies partially verified from source code.

**Depends On:**
- Authentication — Sanctum (Verified)
- Role & Permission — uses `assignRole`, `removeRole`, `syncRoles` on User model (Verified)
- Media Lifecycle — user images via Spatie MediaLibrary (Verified)

**Used By:**
- Dashboard — admin users manage the dashboard (Not verified)
- User Management screen (Not verified)

**Regression Required When Changed:**
- UserControllerTest
- RoleAndPermissionTest (if permission changes)

**Blocking Dependencies:**
None

**Current Status:**
Not Started

---

## Categories

**Purpose:**
Manage product categorization.

**Dependency Confidence:**
Dependencies partially verified from source code.

**Depends On:**
- Authentication — Sanctum (Verified)
- Media Lifecycle — Spatie MediaLibrary on Category model (Verified)
- Permissions — `permission:` middleware in CategoryController (Verified)

**Used By:**
- Products — Category hasMany Products relation (Verified)
- Home — categories displayed on homepage (Not verified)
- Search — category filter in search (Not verified)
- Coupons — coupon belongsTo category (Verified)

**Regression Required When Changed:**
- Categories
- Products
- Search
- Home
- Coupons

**Blocking Dependencies:**
None

**Current Status:**
Production Ready

---

## Brands

**Purpose:**
Manage product brands.

**Dependency Confidence:**
Dependencies partially verified from source code.

**Depends On:**
- Authentication — Sanctum (Verified)
- Media Lifecycle — Spatie MediaLibrary on Brand model (Verified)
- Permissions — `permission:` middleware in BrandController (Verified)

**Used By:**
- Products — Brand hasMany Products relation (Verified)

**Regression Required When Changed:**
- Brands
- Products

**Blocking Dependencies:**
None

**Current Status:**
Production Ready

---

## Products

**Purpose:**
Manage product catalog — create, update, delete, search, filter.

**Dependency Confidence:**
Dependencies partially verified from source code.

**Depends On:**
- Authentication — Sanctum (Verified)
- Categories — belongsTo Category (Verified)
- Brands — belongsTo Brand (Verified)
- Media Lifecycle — Spatie MediaLibrary on Product model (Verified)
- Pricing — Runtime Pricing Architecture via `ProductPricingService` (Verified)

**Used By:**
- Cart — CartItem belongsTo Product (Verified)
- Checkout — order items reference products (Verified)
- Search — search index includes products (Verified)
- Home — featured products on homepage (Not verified)
- Orders — order items reference products (Verified)
- Wishlist — wishlist items reference products (Verified)
- Flash Sales — flash sale products reference products (Verified)
- Promotions — promotion rules apply to products (Verified)
- Coupons — coupon conditions apply to products (Verified)

**Regression Required When Changed:**
- Products
- Cart
- Orders
- Search
- Home
- Flash Sales
- Promotions

**Blocking Dependencies:**
None

**Current Status:**
Production Ready (Phase 1)

---

## Cart

**Purpose:**
Manage shopping cart — add, remove, update items, calculate totals.

**Dependency Confidence:**
Dependencies partially verified from source code.

**Depends On:**
- Authentication — Sanctum (Verified)
- Products — CartItem belongsTo Product (Verified)
- Pricing — uses runtime pricing pipeline (Verified)

**Used By:**
- Checkout — cart converts to order (Verified)
- Orders — order origin is cart checkout (Verified)

**Regression Required When Changed:**
- Cart
- Orders
- Checkout

**Blocking Dependencies:**
None

**Current Status:**
Production Ready

**Notes:**
- RateLimiter::for('cart') registered at RouteServiceProvider.php configured at 20 req/min per user
- English cart.inventory.* translation keys added
- One cart per user (`carts.user_id` UNIQUE); `PUT /update-item` requires `item.operation` (increment/decrement); bulk-items is non-atomic with per-item `failed_items`; clear-cart coupon warning returns HTTP 200 + success:true
- Rev 2 (2026-07-29): Bulk-items shipping_method made optional with default; non-existent products skipped gracefully
- Rev 3 (2026-07-29): Bulk-items per-item error handling — stock failures skip individual items, reported in `failed_items` array
- Rev 4 (2026-08-04): Full documentation audit of `api-desc/cart/` (all 12 files) — no application code changed. Test re-run blocked by global bootstrap error (`Class "Role" not found` at Routes.php:699); last verified 61/65 + 8/8. Open production bugs: BUG-CART-001/002/003/006/007 (see `api-desc/cart/bug-report.md`).
- Rev 5 (2026-08-04): Fixed global test-bootstrap ParseError at `Routes.php:493` (botched comment-out left a dangling array literal) — root cause of the `Class "Role" not found` symptom. Comment-only fix; route behavior unchanged. Full cart suite re-ran: CartApiTest 75/80 + CartExpirationTest 8/8 (83/88 PASS, 5 pre-existing failures: coupon apply, gift promotion, finalization x2, is_gift exposure).

---

## Wishlist

**Purpose:**
Manage user wishlists — add, list, toggle, remove (including per-variant entries), guest-safe in-wishlist check, and a paginated my-wishlists endpoint.

**Dependency Confidence:**
Dependencies partially verified from source code.

**Depends On:**
- Authentication — Sanctum; all wishlist routes except `in_wishlist` behind `auth:sanctum` (Verified)
- Products — `wishlists.product_id` FK to products (Verified)
- Product Variants — `wishlists.product_variant_id` nullable FK (Verified)
- Pricing — WishlistResource reads Product accessors (`current_price`, `price_after_discount`, `price_after_flash_sale`) backed by `ProductPricingService` (Frozen ADR) (Verified)
- Translation System — `MESSAGE.ADDED_TO_WISHLIST_SUCCESSFULLY`, `MESSAGE.REMOVED_FROM_WISHLIST_SUCCESSFULLY`, `ERROR.ALREADY_ADDED_TO_WISHLIST_FOR_THIS_PRODUCT` (Verified)

**Used By:**
- Product detail page — wishlist heart + `in_wishlist` check (Not verified)
- Home / product listing — heart icon from `in_wishlist` field (Not verified)
- Dedicated wishlist UI — `GET /my-wishlists` paginated list (Not verified)

**Regression Required When Changed:**
- Wishlist (WishlistApiTest)
- Products (ProductController myWishlists)
- ProductPricing (pricing accessors consumed by WishlistResource)

**Blocking Dependencies:**
None

**Current Status:**
Production Ready

**Notes:**
- Rev 1 (2026-08-04): Full investigation + hardening of `api-desc/front/wishlist/` (11 files). Fixed 7 bugs: missing `auth:sanctum` on all wishlist routes (BUG-WISH-001), `index()` user-scoping data leak (BUG-WISH-002), variant removal `variant_id`/`product_variant_id` mismatch (BUG-WISH-003), unregistered `show`/`update` routes 500 → restricted apiResource (BUG-WISH-004), Prettus `= NULL` predicate never matching (BUG-WISH-005), `requiredIf` + `sometimes` validation bypass (BUG-WISH-006), `myWishlists` raw paginator → ProductResource collection (BUG-WISH-007). Open: no unique DB constraint on `(user_id, product_id, product_variant_id)` (BUG-WISH-008); `in_wishlist` is product-level, ignores variant by design (BUG-WISH-009).
- New test suite `tests/Feature/WishlistApiTest.php` — 36 tests / 106 assertions, all passing. Setup conditionally creates `wishlists` + `attribute_product` tables (pivot uses singular table name).

---

## Orders

**Purpose:**
Manage customer orders — create, update status, track, list.

**Dependency Confidence:**
Dependencies partially verified from source code.

**Depends On:**
- Authentication — Sanctum (Verified)
- Cart — order created from cart (Verified)
- Products — OrderItem belongsTo Product (Verified)
- Pricing — order totals use pricing pipeline (Verified)
- Payment System — payment gateway integration (Verified)

**Used By:**
- Refunds — refund belongsTo Order (Verified)
- Invoices — invoice generated from order (Verified)
- Dashboard Analytics — order stats displayed (Not verified)

**Regression Required When Changed:**
- Orders
- Refunds
- Invoices

**Blocking Dependencies:**
None

**Current Status:**
Not Started

---

## Coupons

**Purpose:**
Manage discount coupons — create, validate, approve, apply to orders. Includes per-user assignment management for restricted coupons.

**Dependency Confidence:**
Dependencies partially verified from source code.

**Depends On:**
- Authentication — Sanctum (Verified)
- Products — coupon conditions on products (Verified)
- Categories — coupon conditions on categories (Verified)
- Permissions — `permission:` middleware in CouponController + CouponAssignmentController (Verified)
- CouponAssignmentRepository — CRUD for per-user assignments (Verified)
- CouponAssignmentRequest/UpdateCouponAssignmentRequest — validation (Verified)
- CouponAssignmentResource — computed fields (remaining, is_expired) (Verified)

**Used By:**
- Cart — coupon applied in cart (Verified)
- Orders — coupon applied to order (Verified)
- Checkout — coupon validation during checkout (Verified)

**Regression Required When Changed:**
- Coupons (admin CRUD + assignment CRUD)
- Cart (coupon consumption)
- Orders (coupon checkout)

**Blocking Dependencies:**
None

**Current Status:**
Production Ready (admin CRUD layer complete)

**Notes:**
- CouponAssignment admin CRUD (Revision 1) complete as of 2026-07-25 — 43 new tests, 151 assertions
- PermissionSeeder needs to be updated with 4 new coupon assignment permissions (deferred)
- Existing consumption flow (apply, checkout, usage recording) unchanged — backward compatible

---

## Flash Sales

**Purpose:**
Manage flash sale events — time-limited discounts on products.

**Dependency Confidence:**
Dependencies partially verified from source code.

**Depends On:**
- Authentication — Sanctum (Verified)
- Products — flash sale products reference products (Verified)
- Pricing — flash sale pricing via ProductPricingService (Verified)
- Permissions — `permission:` middleware in FlashSaleController (Verified)

**Used By:**
- Cart — flash sale pricing applied in cart (Verified)
- Products — pricing enrichment includes flash sales (Verified)

**Regression Required When Changed:**
- Flash Sales
- Products
- Cart
- Orders

**Blocking Dependencies:**
None

**Current Status:**
Production Ready

---

## Promotions

**Purpose:**
Manage promotions and discount rules.

**Dependency Confidence:**
Dependencies partially verified from source code.

**Depends On:**
- Authentication — Sanctum (Verified)
- Products — promotion rules apply to products (Verified)
- Pricing — promotion pricing via ProductPricingService (Verified)
- Permissions — `permission:` middleware in PromotionController (Verified)

**Used By:**
- Cart — promotion pricing applied in cart (Verified)
- Products — pricing enrichment includes promotions (Verified)

**Regression Required When Changed:**
- Promotions
- Products
- Cart

**Blocking Dependencies:**
None

**Current Status:**
Not Started

---

## Contacts

**Purpose:**
Manage contact messages and replies — create, list, filter (read/unread/replied), reply, delete, bulk delete.

**Dependency Confidence:**
Dependencies partially verified from source code.

**Depends On:**
- Authentication — Sanctum (Verified)
- Permissions — `permission:` middleware on admin endpoints (Verified)
- Translation System — constant keys resolved via `__()` and `translateNotice()` (Verified)

**Used By:**
- Contact Forms — public contact form submission (Verified)
- Admin Notifications — `ContactMessageReceived` event triggers `NewContactMessageNotification` (Verified)
- Notifications — database + broadcast notifications for new contact messages (Verified)

**Regression Required When Changed:**
- Contacts
- Notifications (if event/listener structure changes)

**Blocking Dependencies:**
None

**Current Status:**
Production Ready

---

## Contacts

**Purpose:**
Manage contact messages and replies — create, list, filter (read/unread/replied), reply, delete, bulk delete.

**Dependency Confidence:**
Dependencies partially verified from source code.

**Depends On:**
- Authentication — Sanctum (Verified)
- Permissions — `permission:` middleware on admin endpoints (Verified)
- Translation System — constant keys resolved via `__()` and `translateNotice()` (Verified)

**Used By:**
- Contact Forms — public contact form submission (Verified)
- Admin Notifications — `ContactMessageReceived` event triggers `NewContactMessageNotification` (Verified)
- Notifications — database + broadcast notifications for new contact messages (Verified)

**Regression Required When Changed:**
- Contacts
- Notifications (if event/listener structure changes)

**Blocking Dependencies:**
None

**Current Status:**
Production Ready

---

## Site Reviews

**Purpose:**
Website/site-wide customer reviews with a pending → approved/rejected moderation workflow. Customers submit a rating (1–5), optional title, and comment. Only approved reviews are publicly visible.

**Dependency Confidence:**
Dependencies partially verified from source code.

**Depends On:**
- Authentication — Sanctum; `POST /api/v1/general/site-reviews` behind `auth:sanctum` (Verified)
- User model — `Marvel\Database\Models\User`; `site_reviews.user_id` FK and `moderated_by` FK to users (Verified)
- Permissions — `permission:` middleware on admin endpoints (`view-site-reviews`, `approve-site-reviews`, `reject-site-reviews`) (Verified)
- Translation System — constant keys in `packages/marvel/config/constants.php` resolved via `__('message.' . ...)` (Verified)
- FrontendResource cache — `FrontendResource::SITE_REVIEWS` tag for public list caching + flush on moderation (Verified)

**Used By:**
- Frontend home / reviews UI — public `GET /api/v1/general/site-reviews` (Not verified)
- Admin Dashboard — `Marvel\Http\Controllers\SiteReviewController` list/detail/approve/reject (Verified)

**Regression Required When Changed:**
- Site Reviews (`tests/Feature/SiteReviews/`)
- Permissions (permission seeder + middleware checks)
- Authentication (customer store endpoint)

**Blocking Dependencies:**
None

**Current Status:**
Production Ready

**Notes:**
- Revision 1 (2026-08-10): Full implementation — service layer in `app/Services/SiteReview/SiteReviewService.php`, customer controller in `app/Http/Controllers/Api/General/SiteReviewController.php`, admin controller in `packages/marvel/src/Http/Controllers/SiteReviewController.php`. New reviews always start as `pending`; customers can never set `status`, `moderated_by`, or `moderated_at`. Only `pending → approved` and `pending → rejected` transitions allowed (DB transaction + status guard in service). Admin list/detail eager-loads `user` and `moderator` to display the actual admin name (no N+1). 3 permission constants added (`VIEW_SITE_REVIEWS`, `APPROVE_SITE_REVIEWS`, `REJECT_SITE_REVIEWS`) + PermissionSeeder + en/ar translations. 54 tests / 141 assertions all passing.
- Revision 2 (2026-08-10): Full API investigation (`api-desc/siteReview/`, 12 files). Fixed 2 verified bugs: BUG-SR-001 (High) non-numeric `{id}` on the 3 admin `{id}` routes (`show`/`approve`/`reject`) caused HTTP 500 TypeError — added `->whereNumber('id')` route constraints in `packages/marvel/src/Rest/Routes.php`; BUG-SR-002 (Medium) unvalidated `limit` (`?limit=-5` → SQL LIMIT -5 → 409) — `index()` now normalizes via `$limit = max(1, min((int) $request->query('limit', 15), 100))`. Added `tests/Feature/SiteReviews/SiteReviewBugRegressionTest.php` (4 tests). Full suite now 58 tests / 152 assertions all passing.

---

## Payment System

**Purpose:**
Process payments through configured gateways.

**Dependency Confidence:**
Dependencies partially verified from source code.

**Depends On:**
- Authentication — Sanctum (Verified)
- Orders — payment attached to order (Verified)

**Used By:**
- Checkout — payment processing during checkout (Verified)
- Refunds — refund processes payment reversal (Verified)

**Regression Required When Changed:**
- Payment tests
- Orders
- Refunds

**Blocking Dependencies:**
None

**Current Status:**
Not Started
