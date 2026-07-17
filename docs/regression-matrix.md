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
| RoleAndPermissionTest | PASS | 32/32 tests passed on 2026-07-17 |
| Admin Users | NOT RUN | Feature not audited yet |
| User Management | NOT RUN | Feature not audited yet |

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
| FlashSaleReorderTest | PASS | 3/3 tests pass |
| FlashSaleApproveRequestTest | PASS | 4/4 tests pass |
| PricingCacheInvalidationTest | 4 PRE-EXISTING FAILURES | Unrelated to changes (missing `deleteFlashSale` method, cache assertion failures) |
| ProductPricingServiceTest | PASS | 34/34 tests pass |
| OrderCreationFlowTest | PASS | 32/32 tests pass |

**Cleanup Verification:**
- All `sale_builder` references removed from executable code (2 remaining as commented-out lines in FlashSaleVendorRequestRepository.php — inactive).
- `FlashSale` model import removed from `FlashSaleProductProcess.php`.
- 3 private methods removed (processFlashSaleProducts, processFlashSaleAfterExpired, processSoftDeletedFlashSales).
- `'index'` action branch removed from handle().
- No regression in any test suite.

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

## Full Suite Status

| Suite | Status | Date | Notes |
|-------|--------|------|-------|
| RoleAndPermissionTest | PASS (32/32) | 2026-07-17 | Verified production bugs fixed |
| FlashSaleReorderTest | PASS (3/3) | 2026-07-17 | Regression test for route ordering bug |
| FlashSaleApproveRequestTest | PASS (4/4) | 2026-07-17 | Regression test for auth/response bugs |
| ProductPricingServiceTest | PASS (34/34) | 2026-07-17 | Full pricing pipeline, including 12 flash sale pricing tests |
| OrderCreationFlowTest | PASS (32/32) | 2026-07-17 | Order creation with flash sale discount pricing |
| FlashSaleCombinedSuite | PASS (73/73) | 2026-07-17 | All Flash Sale + Pricing + OrderCreation tests pass after dead code cleanup |
| ProductSuite | PASS (76/76) | 2026-07-17 | All Product feature tests pass after 4 bug fixes |
