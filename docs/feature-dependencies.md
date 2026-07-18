# Feature Dependency Graph

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
Not Started

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
Not Started

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
Manage discount coupons — create, validate, approve, apply to orders.

**Dependency Confidence:**
Dependencies partially verified from source code.

**Depends On:**
- Authentication — Sanctum (Verified)
- Products — coupon conditions on products (Verified)
- Categories — coupon conditions on categories (Verified)
- Permissions — `permission:` middleware in CouponController (Verified)

**Used By:**
- Cart — coupon applied in cart (Verified)
- Orders — coupon applied to order (Verified)
- Checkout — coupon validation during checkout (Verified)

**Regression Required When Changed:**
- Coupons
- Cart
- Orders

**Blocking Dependencies:**
None

**Current Status:**
Not Started

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
