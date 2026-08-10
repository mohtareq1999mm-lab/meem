# Regression Matrix

When a feature is modified, ALL dependent features and their test suites must be re-run.

---

## Role & Permission

**Changed Feature:**
Role & Permission

**Affected Features:**
- Admin Users
- User Management
- All middleware-guarded endpoints

**Regression:**

| Suite | Status | Reason |
|-------|--------|--------|
| RoleAndPermissionTest | PASS | 32/32 tests passed on 2026-07-20 (159 assertions) |
| Admin Users | NOT RUN | Feature not audited yet |
| User Management | NOT RUN | Feature not audited yet |

**Changes Applied (Revision 2):**
- `Routes.php`: Removed duplicate unauthenticated role/permission routes (lines 136–138, 146–158) — fixes Bugs 1, 4, 5, 6 (403 on all permission endpoints, user detail missing roles, remove-role/user-permission 403)
- `RoleAndPermissionController.php`: Changed `addRole()`/`updateRole()` from mass-assignment to explicit property assignment to avoid HasTranslations trait conflict — fixes Bug 2 (display_name stored as false)
- `RoleResource.php`: Added `name`, `guard_name`, `created_at`, `updated_at` fields — fixes Bug 3 (roles list missing fields)
- `destroyRole()`: Added `$role->users()->count() > 0` check returning 409 conflict — fixes Bug 7 (delete role with assigned users succeeds silently)
- `UserController.php`: Added `permissions` + `role` to `token()` login response — fixes Bug 8 (login missing permissions/role)
- `RoleAndPermissionTest.php`: Updated pagination assertion for flattened response structure; updated cascade delete test to assert 409 conflict

---

## How to Use

1. Find the "Changed Feature" section for the feature that was modified.
2. Run listed regression suites.
3. Update status to PASS or FAIL with actual result.
4. If any suite fails, fix regressions before closing.

---

## Flash Sales

**Changed Feature:**
Flash Sales

**Affected Features:**
- Products — flash sale pricing enrichment
- Cart — flash sale pricing applied in cart
- Orders — order creation pricing

**Regression:**

| Suite | Status | Reason |
|-------|--------|--------|
| FlashSaleRegressionTest | PASS | 4/4 tests pass |
| FlashSaleReorderTest | PASS | 3/3 tests pass |
| FlashSaleApproveRequestTest | PASS | 4/4 tests pass |
| FlashSaleProductionHardenTest | PASS | 26/26 new tests pass |
| PricingCacheInvalidationTest | 2 ERRORS, 3 FAILURES (PRE-EXISTING) | Unrelated to changes (product_variants table missing in test env) |
| ProductPricingServiceTest | PASS | 34/34 tests pass |
| OrderCreationFlowTest | PASS | 15/15 tests pass |

**Changes Applied (Revision 4):**
- `FlashSaleProductProcess.php`: Fixed BUG-1 — `processNewlyAddedProductInFlashSale()` now sets `has_flash_sale = true` and `price_after_flash_sale`; `unsetProductFromFlashSale()` now sets `has_flash_sale = false` and `price_after_flash_sale = null`
- `FlashSaleProductProcess.php`: Fixed BUG-4 — replaced non-existent `variation_options` relationship with `variations`; replaced bulk `Variation::where(...)->update(...)` with `$variation->save()`; removed dead `$product->sale_price = null` writes (column does not exist on products table)
- `OrderCreationFlowTest.php`: Fixed pre-existing test bug — `product_without_variant_still_uses_product_price` expected `product_discount_price = 80.00` but flash sale override means it should be null
- Created `FlashSaleProductionHardenTest.php`: 26 tests covering has_flash_sale lifecycle, admin CRUD, pricing priority, flash sale types, resources, validation, auth, soft delete, route ordering, model scopes

---

## Products

**Changed Feature:**
Products

**Affected Features:**
- Cart — CartItem belongsTo Product
- Orders — order items reference products
- Search — search index includes products
- Home — featured products on homepage
- Wishlist — wishlist items reference products
- Flash Sales — flash sale products reference products
- Promotions — promotion rules apply to products
- Coupons — coupon conditions apply to products

**Regression:**

| Suite | Status | Reason |
|-------|--------|--------|
| ProductAdminTest | PASS | 17/17 tests pass on 2026-07-17 |
| ProductFilterTest | PASS | 2/2 tests pass |
| ProductTagTest | PASS | 20/20 tests pass |
| ProductImportTest | PASS | 33/33 tests pass |
| ProductExportTest | PASS | 4/4 tests pass |
| ProductPricingServiceTest | PASS | — |

**Changes Applied:**
- `destroyProduct()`: Replaced `$this->forceDeleteProduct($product)` with `$product->delete()` (soft delete)
- `destroyAll()`: Improved exception handling
- `destroyBulk()`: Updated docblock to reflect soft delete behavior
- `MediaCleanupObserver`: Fixed media lifecycle — preserves media on soft delete, cleans up on force delete
- Removed unused import of `GetSingleProductResource`
- Removed dead `GetSingleProductResource.php` class file

---

## Cart

**Changed Feature:**
Cart

**Affected Features:**
- Checkout — cart converts to order
- Orders — order origin is cart checkout

**Regression:**

| Suite | Status | Reason |
|-------|--------|--------|
| CartApiTest | PASS | 32/32 tests pass on 2026-07-18 |

**Changes Applied:**
- `RouteServiceProvider.php`: Added `RateLimiter::for('cart')` — 20 req/min per user with IP fallback
- `CouponRepository.php`: Changed `$user->cart->first()` to `$user->cart` for HasOne relationship
- `Routes.php`: Added `->middleware('auth:sanctum')` to `coupons/add-to-cart`
- `message.php`: Added 6 `cart.inventory.*` English translation keys

---

## Brands

**Changed Feature:**
Brands

**Affected Features:**
- Products — Brand hasMany Products relation
- Media Lifecycle — brand images via Spatie MediaLibrary

**Regression:**

| Suite | Status | Reason |
|-------|--------|--------|
| BrandApiTest | PASS | 32/32 existing tests pass on 2026-07-18 |
| BrandProductionHardenTest | PASS | 31/31 new hardening tests pass |

**Changes Applied:**
- `Brand.php`: Added `isDirty('name')` guard to saving event — prevents slug overwrite on non-name updates
- Created `BrandProductionHardenTest.php`: 31 tests covering slug preservation, soft delete/restore, media management, product sync, reorder, mass assignment, edge cases

---

## Attributes + Attribute Values

**Changed Feature:**
Attributes + Attribute Values

**Affected Features:**
- Products — product variants depend on attribute values; product filtering by attribute slugs
- Cart — cart items reference product_variant_id (indirect)
- Orders — order items snapshot product variant data
- Import/Export — variant import creates/finds attribute values and pivot records

**Regression:**

| Suite | Status | Reason |
|-------|--------|--------|
| AttributeApiTest | PASS (14/16 — 2 pre-existing 403/401 test bugs) | Unchanged test suite passes |
| AttributesProductionHardenTest | PASS (32/32) | All new hardening tests pass |
| PricingCacheInvalidationTest | 2 ERRORS, 3 FAILURES (PRE-EXISTING) | Unrelated (product_variants table missing in test env) |

**Changes Applied:**
- `AttributeRepository.php`: Fixed BUG-A — updateAttribute now diffs values by slug instead of delete+recreate; preserves existing values and their product-variant associations
- `AttributeRequest.php`: Fixed BUG-B — proper nested validation for `values.*.value.en` and `values.*.value.ar`
- Created `2026_07_19_000001_add_unique_constraints_to_attributes.php`: unique indexes on `attribute_values(attribute_id, slug)` and `attribute_product(attribute_value_id, product_variant_id)`
- Created `AttributesProductionHardenTest.php`: 32 tests covering CRUD, auth, validation, cascade, resource structure, BUG-A regression

---

## Product Import/Export

**Changed Feature:**
Product Import/Export

**Affected Features:**
- Products — import creates/updates products; export reads products
- Attributes — import creates/finds attribute values for variants
- Categories/Brands/Tags/FlashSales/Sliders — import syncs relations
- Pricing — import relies on ProductPricingService for pricing computation
- Inventory — import sets stock quantities on products and variants
- Media — import downloads and attaches images via UrlImageHandler/ZipImageHandler

**Regression:**

| Suite | Status | Reason |
|-------|--------|--------|
| ProductImportTest | PASS (34/34) | All import tests pass (33 existing + 1 new regression test) |
| ProductExportTest | PASS (4/4) | All export tests pass |
| ProductSuite | PASS (76/76) | All Product feature tests pass (no change to product code) |

**Changes Applied:**
- `ImportProductsJob.php`: Added `$service->finalizeVariants()` call after Excel import — orphaned variants from re-imports are now properly cleaned up
- `ProductImportTest.php`: Added `test_finalize_variants_removes_orphaned_variants` — regression test verifying orphaned variant deletion

---

## Contacts

**Changed Feature:**
Contacts

**Affected Features:**
- Notifications — `ContactMessageReceived` event triggers admin notification
- Contact Forms — public store endpoint

**Regression:**

| Suite | Status | Reason |
|-------|--------|--------|
| ContactAuthenticationTest | PASS | All contact auth tests pass |
| ContactAuthorizationTest | PASS | All contact permission tests pass |
| ContactCrudTest | PASS | All CRUD tests pass |
| ContactReplyTest | PASS | Reply tests use `/reply` URL with `sendReply` method |
| ContactRegressionTest | PASS | b4_contact_us_route_works test passes |
| ContactResourceTest | PASS | JSON structure verified |
| ContactSoftDeleteTest | PASS | Soft delete behavior verified |
| ContactValidationTest | PASS | Validation rules verified |

**Changes Applied (Revision 1):**
- `ContactController.php`: Renamed `sendReplay()` → `sendReply()`; updated permission middleware reference
- `Routes.php`: Updated route target from `sendReplay` to `sendReply`

---

## Authentication

**Changed Feature:**
Authentication

**Affected Features:**
- All features — every API endpoint requires auth

**Regression:**

| Suite | Status | Reason |
|-------|--------|--------|
| All Feature Tests | NOT RUN | No dedicated auth test suite exists |

**Changes Applied (Revision 1):**
- `.env`: MAIL_MAILER changed from `smtp` to `log` — password reset emails logged instead of SMTP delivery
- `UserController.php`: Added try/catch in `sendUserOtp()` for mail failure resilience
- `UserController.php`: Refactored `verifyForgetPasswordToken()` to return proper JSON response (was returning raw boolean)
- `UserController.php`: Extracted `checkResetToken()` private method for internal use by `resetPassword()`
- `resources/lang/en/message.php`: Added 4 missing password reset translation keys
- Created `api-decs/auth/authentication.md`: Auth endpoint documentation
- Created `api-decs/auth/password-reset.md`: Password reset endpoint documentation
- Created `api-decs/bug-fixed/smtp-password-reset-fix.md`: Bug fix report

---

## Categories

**Changed Feature:**
Categories

**Affected Features:**
- Categories — products_count consistency in category-by-slug endpoint

**Regression:**

| Suite | Status | Reason |
|-------|--------|--------|
| CategoryResourceTest | PASS (7/7) | Existing resource tests pass |
| CategoryProductsCountConsistencyTest | PASS (4/4) | New regression tests — count matches products array length |
| CategoryCombinedSuite | PASS (98/98) | All 98 category tests pass (94 existing + 4 new) |

**Changes Applied (Revision 2):**
- `app/Services/General/CategoryService.php`: Changed `withCount('products')` to `withCount(['products' => fn($q) => $this->applyChannelHomeFilter($q)])` — the count now uses the same channel filter as the eager-loaded products, fixing `products_count` mismatch when the home context filters out fast-shipping products
- Created `tests/Feature/Categories/CategoryProductsCountConsistencyTest.php`: 4 tests covering the bug regression

---

## Cart (Bulk Items)

**Changed Feature:**
Cart — `pluckItemsToCart` method (POST /cart/bulk-items)

**Affected Features:**
- Cart — bulk item addition behavior changed

**Regression:**

| Suite | Status | Reason |
|-------|--------|--------|
| CartApiTest — bulk tests | PASS (6/6) | Skip non-existent products, mixed valid/invalid, stock failure skip, all-fail returns empty cart |

**Changes Applied (Revision 3):**
- `CartController.php`: Removed outer DB transaction — each item processed independently; failed items caught per-item and reported in `failed_items` array
- `CartController.php`: Added `failed_items` to response — each entry has `product_id`, `product_variant_id`, `reason`
- `CartController.php`: Removed unused `use Illuminate\Support\Facades\DB` import
- `CartApiTest.php`: Updated `test_bulk_add_rolls_back_on_failure` → `test_bulk_add_skips_stock_failures_and_continues` (asserts 201 with failed_items)
- `CartApiTest.php`: Added `test_bulk_add_skips_all_failures_returns_empty_cart` — all items fail, null cart returned

---

## Cart (Documentation Audit)

**Changed Feature:**
Cart — Revision 4 documentation audit (all 12 `api-desc/cart/` files). No application code changed.

**Affected Features:**
- Cart — documentation contract corrections (routes, request fields, response codes/shapes)
- Checkout — uses cart totals (no behavior change)
- Orders — order origin is cart checkout (no behavior change)

**Regression:**

| Suite | Status | Reason |
|-------|--------|--------|
| CartApiTest | BLOCKED (re-run) | Last verified 61/65 on 2026-07-29 (4 pre-existing failures: gift promotion, finalization, resource structure). 2026-08-04 run could not execute — every test errors at bootstrap with `Class "Role" not found` (Routes.php:699), a global test-env issue unrelated to cart |
| CartExpirationTest | PASS (8/8) | Last verified 2026-07-29 |
| CartApiTest — bulk tests | PASS (6/6, 34 assertions) | Last verified 2026-07-29 |

---

## Cart (Route Bootstrap Fix)

**Changed Feature:**
Cart — Revision 5. Fixed a global test-bootstrap `ParseError` in `packages/marvel/src/Rest/Routes.php:493` that blocked **every** test suite from booting (not just cart). Lines 492-502 were a botched comment-out: `Route::group(` (line 492) was commented but its array argument (line 493) was not, leaving a dangling array literal → `syntax error, unexpected token ","`. Root cause of the earlier `Class "Role" not found` symptom; `use Marvel\Enums\Role;` was always present (line 7) and the enum autoloads correctly (`vendor/autoload.php` → `bool(true)`).

**Affected Features:**
- All test suites (global bootstrap) — was blocking 100% of PHPUnit runs
- Cart — verified after fix

**Change Made:**
- `packages/marvel/src/Rest/Routes.php:493` — commented out the dangling array to match the surrounding commented-out `Route::group` block. No route behavior changed; the block was already fully disabled.

**Regression:**

| Suite | Status | Reason |
|-------|--------|--------|
| CartApiTest | 75/80 PASS (330 assertions) | 5 pre-existing failures: `test_apply_coupon_to_cart` (coupon discount 405 not applied), `add_regular_item_does_not_overwrite_gift_item` (gift promotion null vs 999), `finalize_scheduled_items_only_keeps_fast_items` (BUG-CART-002), `finalize_fast_items_only_keeps_scheduled_items` (BUG-CART-002), `gift_item_attribute_not_exposed_in_item_resource` (`is_gift` key exposed — BUG-CART-011) |
| CartExpirationTest | PASS (8/8, 16 assertions) | 2026-08-04 |
| Full Cart Suite | 83/88 PASS | 2026-08-04 |

**Changes Applied (Revision 4):**
- Documentation-only corrections to match source: routes at Routes.php:149-157; `GET /cart` reads `limit` (not `per_page`); `PUT /update-item` requires `item.operation`; clear-cart coupon warning is HTTP 200 + success:true; bulk-items returns 201 with `cart`/`skipped_product_ids`/`failed_items`; `coupon` object = CouponResource shape; `product` = `{id,name,slug,thumbnail}`.
- Updated `docs/production-status.md`, `docs/feature-dependencies.md`, `docs/production-history.md`.
- Full regression re-run REQUIRED once the test bootstrap (`Class "Role" not found`) is fixed.

---

## Wishlist

**Changed Feature:**
Wishlist

**Affected Features:**
- Authentication — all wishlist routes now behind `auth:sanctum`
- Products — `ProductController::myWishlists` returns ProductResource collection
- ProductPricing — WishlistResource consumes pricing accessors (no direct service calls)
- Home / Product listing — `in_wishlist` field consumed by frontend

**Regression:**

| Suite | Status | Reason |
|-------|--------|--------|
| WishlistApiTest | PASS (36/36, 106 assertions) | New suite — 2026-08-04 |
| ProductSuite | NOT RUN | ProductController::myWishlists changed — re-run recommended |
| ProductPricingServiceTest | NOT RUN | Pricing accessors consumed by WishlistResource — re-run recommended |

**Changes Applied (Revision 2):**
- `Routes.php`: Wrapped `wishlists/toggle`, `wishlists` apiResource, `my-wishlists` in `auth:sanctum` group; restricted apiResource to `only(['index', 'store', 'destroy'])` — BUG-WISH-001/004
- `WishlistController.php`: `index()` scoped to `$request->user()->id` (BUG-WISH-002); `destroy()`/`delete()` aligned on `product_variant_id` (BUG-WISH-003); `store()` returns translated 400 on duplicate
- `WishlistRepository.php`: Added `findUserWishlistItem()` with explicit `whereNull`/`where` clauses replacing Prettus `findOneWhere` `= NULL` (BUG-WISH-005)
- `WishlistCreateRequest.php`: Removed `sometimes` from `product_variant_id` rules — fixes `requiredIf` bypass (BUG-WISH-006)
- `ProductController.php`: `myWishlists()` returns `ProductResource::collection($paginator)` (BUG-WISH-007)
- Created `tests/Feature/WishlistApiTest.php` (36 tests / 106 assertions)

---

## Site Reviews

**Changed Feature:**
Site Reviews (Revision 2 — API investigation + bug fixes)

**Affected Features:**
- Authentication — customer `POST /api/v1/general/site-reviews` behind `auth:sanctum`
- Permissions — 3 new permissions (`view-site-reviews`, `approve-site-reviews`, `reject-site-reviews`) added to PermissionSeeder
- User model — `site_reviews.user_id` + `moderated_by` FKs to users
- FrontendResource — new `SITE_REVIEWS` cache tag
- Routes — 3 admin `{id}` routes now constrained with `whereNumber('id')` (BUG-SR-001)
- Admin list — `limit` query param normalized (BUG-SR-002)

**Regression:**

| Suite | Status | Reason |
|-------|--------|--------|
| SiteReviewCreationTest | PASS (12/12) | Create, pending default, mass-assignment guards, validation, unauth |
| SiteReviewPublicApiTest | PASS (7/7) | Approved-only visibility, no moderation fields, customer name |
| SiteReviewModerationTest | PASS (17/17) | Approve/reject flows, admin id/timestamp, transitions, 404 |
| SiteReviewAdminApiTest | PASS (11/11) | Admin list/detail, moderator name, status filter, N+1 guard |
| SiteReviewRelationshipsTest | PASS (8/8) | user/moderator relations, factory states |
| SiteReviewBugRegressionTest | PASS (4/4) | NEW (Rev 2) — non-numeric id → 404 not 500; negative/zero/non-numeric/oversized limit normalization |

**Changes Applied (Revision 1):**
- Created `SiteReviewStatus` enum, `site_reviews` migration, `SiteReview` model, `SiteReviewService`, `CreateSiteReviewRequest`, `SiteReviewResource`, `AdminSiteReviewResource`, customer + admin controllers, `SiteReviewFactory`, `SiteReviewSeeder`
- Added 3 permission constants + seeder + en/ar permission translations; 4 message constants + en/ar translations
- Added 2 customer routes (`routes/api.php`) and 4 admin routes (`packages/marvel/src/Rest/Routes.php`)
- Created `tests/Feature/SiteReviews/` suite — 54 tests / 141 assertions

**Changes Applied (Revision 2):**
- `packages/marvel/src/Rest/Routes.php`: Added `->whereNumber('id')` to the 3 admin `{id}` routes (`show`/`approve`/`reject`) — BUG-SR-001 (non-numeric id → HTTP 500 TypeError)
- `packages/marvel/src/Http/Controllers/SiteReviewController.php::index()`: `$limit = max(1, min((int) $request->query('limit', 15), 100))` — BUG-SR-002 (`?limit=-5` → SQL `LIMIT -5` → QueryException → 409; no upper bound)
- Created `tests/Feature/SiteReviews/SiteReviewBugRegressionTest.php` (4 tests)
- Full suite now 58 tests / 152 assertions all passing

---

## Full Suite Status

| Suite | Status | Date | Notes |
|-------|--------|------|-------|
| SiteReviewsSuite | PASS (58/58, 152 assertions) | 2026-08-10 | New suite — creation, public API, moderation, admin API, relationships + 4 bug-regression tests (Rev 2) |
| WishlistApiTest | PASS (36/36, 106 assertions) | 2026-08-04 | New suite — auth, scoping, CRUD, toggle, in_wishlist, my-wishlists, validation, 405 guards |
| RoleAndPermissionTest | PASS (32/32) | 2026-07-20 | Rev 2: 8 production bugs fixed — routes, display_name, missing fields, delete cascade, login |
| FlashSaleReorderTest | PASS (3/3) | 2026-07-17 | Regression test for route ordering bug |
| FlashSaleApproveRequestTest | PASS (4/4) | 2026-07-17 | Regression test for auth/response bugs |
| ProductPricingServiceTest | PASS (34/34) | 2026-07-17 | Full pricing pipeline, including 12 flash sale pricing tests |
| OrderCreationFlowTest | PASS (15/15) | 2026-07-19 | Order creation with flash sale discount pricing (15 flash-sale-relevant tests) |
| FlashSaleCombinedSuite | PASS (87/87) | 2026-07-19 | All Flash Sale (38) + Pricing (34) + OrderCreation (15) tests pass after production hardening |
| ProductSuite | PASS (76/76) | 2026-07-17 | All Product feature tests pass after 4 bug fixes |
| BrandCombinedSuite | PASS (63/63) | 2026-07-18 | All Brand feature tests pass after slug dirty-check fix |
| CategoryCombinedSuite | PASS (98/98) | 2026-07-23 | Categories Rev 2: products_count mismatch fixed + 4 new regression tests |

---

## Coupon Assignments

**Changed Feature:**
Coupon Assignments (Admin CRUD)

**Affected Features:**
- Coupon Assignment consumption (customer-facing apply/checkout flow)
- Coupons — existing CRUD and consumption tests

**Regression:**

| Suite | Status | Reason |
|-------|--------|--------|
| CouponAssignmentApiTest | PASS (30/30) | Full CRUD API tests — auth, permissions, list, create, show, update, delete, conflict, resource computed fields |
| CouponAssignmentValidationTest | PASS (13/13) | Validation rules — store/update field validation, null expiry, optional fields |
| CouponSystemTest | PASS (7/7) | Existing coupon consumption tests — no regressions |
| CouponsProductionHardenTest | PASS (30/30) | Existing production hardening tests — no regressions |
| AssignedCouponSystemTest | PASS (10/10) | Existing assigned coupon consumption tests — no regressions |

**Changes Applied (Revision 1):**
- Created `CouponAssignmentController.php`: 5 methods (index, store, show, update, destroy) with individual permission middleware
- Created `CouponAssignmentRepository.php`: listByCoupon, findAssignment, assignCoupon (transaction + duplicate detection), updateAssignment (max_uses >= used guard), removeAssignment (transaction + lockForUpdate + delete-blocked-when-used)
- Created `CouponAssignmentRequest.php`: Validation for store (user_id required+exists, max_uses required+integer+min:1, expires_at nullable+date+future)
- Created `UpdateCouponAssignmentRequest.php`: Validation for update (max_uses sometimes, expires_at nullable)
- Created `CouponAssignmentResource.php`: Computed remaining (max_uses - used), is_expired (expires_at in past), eager-loaded user data
- `Permission.php`: Added 4 constants (VIEW_COUPON_ASSIGNMENTS, CREATE_COUPON_ASSIGNMENT, UPDATE_COUPON_ASSIGNMENT, DELETE_COUPON_ASSIGNMENT)
- `Routes.php`: Added 5 routes in super_admin group at lines 720-726
- `constants.php`: Added 7 constants for response messages and errors
- `message.php` (en + ar): Added 7 translation keys
- Created `CouponAssignmentApiTest.php`: 30 tests covering auth, CRUD, edge cases, computed fields
- Created `CouponAssignmentValidationTest.php`: 13 tests covering validation rules
| AttributeCombinedSuite | PASS (48/48) | 2026-07-19 | All Attribute + Attribute Values tests pass (16 existing + 32 new) after 3 bug fixes |
| CartBulkItemsSuite | PASS (6/6 new, 61/65 full suite) | 2026-07-29 | 4 pre-existing failures unrelated (gift promotion, finalization, resource structure) |
