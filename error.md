# Architectural Errors / Blockers

Created during the Full API Endpoint / Routes / Permissions / Translations / Architecture Production Audit (2026-08-23).
Only genuine architectural blockers are recorded here. All normal bug fixes were applied directly and are listed in the audit final report / production history.

---

## ERR-001

### Location
- packages/marvel/src/Database/Repositories/RefundRepository.php:53-92
- packages/marvel/src/Http/Resources/GetSingleRefundResource.php:44-82
- packages/marvel/database/migrations/ (refunds table migration ABSENT)
- packages/marvel/database/migrations/2020_06_02_051901_create_marvel_tables.php:179-215 (orders: no customer_id, no amount columns)

### Problem
The entire Refunds REST feature is legacy scaffold that is incompatible with this project's evolved schema:

1. **No migration creates the `refunds` table** anywhere in `packages/marvel/database/migrations/` or `database/migrations/`. Only `refund_policies` and `refund_reasons` exist. On any fresh deployment the whole `/api/v1/refunds` resource fails with "no such table".
2. **POST /api/v1/refunds can never succeed**: `storeRefund()` reads `$order->customer_id` and `$order->amount`, but the real `orders` schema defines neither column (`user_id` and `total_price`/`price` instead). Both values are always `null`; `refunds.customer_id` is NOT NULL → QueryException (409).
3. **GET /api/v1/refunds/{id} always returns 500**: `GetSingleRefundResource::getProductData()` iterates `$order->products`, a relation that does not exist on the Marvel `Order` model, and expects legacy pivot columns `order_product.order_quantity` / `order_product.subtotal` which no migration defines (real columns: `product_quantity`, `product_total_price`). Verified by runtime execution: "foreach() argument must be of type array|object, null given".

### Why It Was NOT Fixed
Making refunds functional requires deciding the refund data model: adding `orders.customer_id`/`orders.amount` (or remapping onto `user_id`/`total_price`), authoring the missing `refunds` migration, and redesigning the refund-detail response contract (`GetSingleRefundResource`) against the current `order_products` schema. That is a product/architecture decision for the Payment System feature (explicitly **Not Started** in docs/production-status.md), not an objective bug fix.

### Risk
- Customers cannot request refunds at all through the API (silent 409/500s).
- If the table is hand-created by ops, GET show() still 500s for everyone.
- Security posture was hardened in this audit regardless (route-level `auth:sanctum`, `show()` customer scoping, inverted super_admin condition fixed in `RefundRepository::storeRefund()` line 68) — see regression tests `tests/Feature/ProductionClosureAuditRegressionTest.php`.

### Recommended Future Resolution
As part of the Payment System implementation: author `create_refunds_table` migration; decide customer identity mapping (`orders.user_id` vs new `customer_id`); rewrite `GetSingleRefundResource` against `orderItems`/`order_products` current columns; add full HTTP coverage including approve/reject wallet flows.

### Current Action
Security fixes applied; functional restoration deferred. Regression tests assert authorization contract only.

---

## ERR-002

### Location
- composer.json:19 (`karim007/laravel-bkash-tokenize`: dev-main)
- vendor/karim007/laravel-bkash-tokenize/src/BkashTokenizeServiceProvider.php:25 (loads routes unconditionally)
- vendor/karim007/laravel-bkash-tokenize/src/routes/bkash_route.php:8-17 (references `App\Http\Controllers\BkashTokenizePaymentController`)
- Missing class: app/Http/Controllers/BkashTokenizePaymentController.php

### Problem
The bKash vendor package registers 6 public routes pointing to an application-side controller class that was never published (the class has never existed in git history). Consequences:

1. `php artisan route:list` fatals with ReflectionException ("Class ... does not exist") — diagnostic tooling broken.
2. The 6 routes are reachable HTTP endpoints that 500 on dispatch (dead public attack surface).
3. `php artisan route:cache` works (deploy pipeline unblocked — verified in this audit after duplicate-name fixes).

### Why It Was NOT Fixed
Both remediations are product/dependency decisions inside the Payment System feature scope (Not Started):
- Publishing the controller (the vendor's intended design) would ACTIVATE demo payment endpoints — including `GET /bkash/create-payment` with a hardcoded amount/currency and hardcoded refund IDs — a security regression worse than the dead routes.
- Adding `"dont-discover"` for the package or removing the dependency changes payment-gateway wiring that may be planned for the Bangladesh market.

### Risk
Low operational risk (route caching/deploy unaffected); medium hygiene risk (broken route:list masks other route diagnostics; 6 dead public URLs).

### Recommended Future Resolution
When Payment System work starts: either publish + properly integrate the controller behind auth, or drop the dependency / add it to `dont-discover`.

### Current Action
No code change performed. Documented.

---

## ERR-003

### Location
- packages/marvel/src/GraphQL/Mutations/{TagMutator,FaqMutator,AttributeMutator,CouponMutator}.php
- packages/marvel/src/Shop.php:20-29 (`Shop::call()` invokes controller actions bypassing constructor middleware)
- packages/marvel/src/Http/Controllers/{TagController,FaqsController,AttributeController,CouponController}.php (constructor permission middleware)

### Problem
Marvel's GraphQL mutation layer executes controller actions via `Shop::call()`, which does not run Laravel constructor middleware. Consequently the permission gates enforced on the REST side (`update-tags`, `update-faq`, `create-attribute`, coupon `super_admin` approval gate) are not enforced for the equivalent GraphQL mutations. The `permission:` slug `super_admin` additionally exists only as a role name (never seeded as a permission), so the REST-side `permission:super_admin` middleware on coupon approve/disApprove could never match anyway.

### Why It Was NOT Fixed
A complete fix means introducing an authorization strategy for the GraphQL execution layer (inline guards per mutator, GraphQL auth directives, or shared policy objects) — an architecture decision about how the GraphQL surface relates to the REST permission model. Partial mitigation WAS applied within existing patterns this audit: `CouponController::approveCoupon()/disApproveCoupon()` now perform an inline `hasPermissionTo(SUPER_ADMIN)` re-check (mirroring `UserController::makeOrRevokeAdmin` and `FaqsController::deleteFaq` precedents), closing the highest-risk bypass (financial coupon approval).

### Risk
Medium: remaining Tag/Faq/Attribute GraphQL mutations allow authenticated users to perform admin CRUD operations that REST gates protect. GraphQL exposure/usage should be audited per deployment.

### Recommended Future Resolution
Adopt one project-wide mechanism (policies or GraphQL directives) and apply to all mutators; seed or remove the `super_admin`-as-permission ambiguity.

### Current Action
Coupon approval bypass closed inline. Remaining mutators documented; no redesign performed.

---

## ERR-004

### Location
- packages/marvel/src/Enums/Permission.php (VIEW_FlASH_SALE casing typos L73-76; VIEW_NOTIFICATTIONS/MANAGE_NOTIFICATTIONS duplicate typos L27-28; singular/plural parallel sets e.g. create-tags L267 vs CREATE_TAG L245)
- database/seeders/PermissionSeeder.php (seeded set reflects the inconsistent slugs)
- packages/marvel/config/constants.php (SHIPMENT_* constants were absent until added this audit)

### Problem
The permission registry contains naming-convention violations and duplicated singular/plural sets (e.g., `create-tags` enforced while `create-tag` sits seeded-but-unused; `view-flash-sale` produced from typo'd constant names; notification constants duplicated with misspellings mapping to identical slugs). Because permissions are persisted in the DB and assigned to roles, renaming slugs invalidates every existing role assignment.

### Why It Was NOT Fixed
Per the task's own rule (#11 / #16 of priorities): renaming seeded permissions requires a coordinated data migration across roles/model_has_permissions and possibly frontend permission maps. Dangerous to perform inside an audit whose mandate forbids breaking existing assignments.

### Risk
Low immediate runtime risk (enforcement uses consistent slugs); ongoing maintenance/confusion risk; typo constants encourage future mismatched references.

### Recommended Future Resolution
Schedule a permission-slug normalization migration (old slug → new slug rename in permissions + role sync), then align enum constants and seeder.

### Current Action
No renames performed. This audit completed the registry where it was incomplete but safe: seeded missing `create-review` / `update-review` (enum constants already existed and were enforced nowhere) and defined the missing SHIPMENT_* message constants.
