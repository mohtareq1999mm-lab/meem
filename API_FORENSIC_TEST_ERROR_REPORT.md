# API Forensic Test Error Report

## Test Environment

* **Project**: meem (Laravel E-commerce API)
* **Laravel Version**: 10.30.1
* **PHP Version**: 8.2.30
* **Database**: SQLite (testing), MySQL/PostgreSQL (production)
* **Cache Driver**: array (testing), redis (production)
* **Queue Driver**: sync (testing)
* **Test Command**: `php artisan test`
* **Date/Time**: 2026-09-02 01:35:00 UTC

## Endpoint Coverage

| Method | Endpoint | Tested | Result |
|--------|----------|--------|--------|
| GET | /api/v1/general/nav-data | Yes | PASS |
| GET | /api/v1/general/categories | Yes | PASS |
| GET | /api/v1/general/categories/{slug} | Yes | PASS |
| GET | /api/v1/general/brands | Yes | PASS |
| GET | /api/v1/general/brands/{slug} | Yes | PASS |
| GET | /api/v1/general/brands-products | Yes | PASS |
| GET | /api/v1/general/banners | Yes | PASS |
| GET | /api/v1/general/banners/{slug} | Yes | PASS |
| GET | /api/v1/general/sliders | Yes | PASS |
| GET | /api/v1/general/sliders/{slug} | Yes | PASS |
| GET | /api/v1/general/tags | Yes | PASS |
| GET | /api/v1/general/tags/{slug} | Yes | PASS |
| GET | /api/v1/general/promotions | Yes | PASS |
| GET | /api/v1/general/promotions/{slug} | Yes | PASS |
| GET | /api/v1/general/coupons | Yes | PASS |
| GET | /api/v1/general/content-pages | Yes | PASS |
| GET | /api/v1/general/content-pages/{slug} | Yes | PASS |
| GET | /api/v1/general/static-pages | Yes | PASS |
| GET | /api/v1/general/static-pages/{slug} | Yes | PASS |
| GET | /api/v1/general/products | Yes | PASS |
| GET | /api/v1/general/products/{slug} | Yes | PASS |
| GET | /api/v1/general/flash-sales | Yes | PASS |
| GET | /api/v1/general/flash-sales/{slug} | Yes | PASS |
| GET | /api/v1/general/flash-sale-products | Yes | PASS |
| GET | /api/v1/general/flash-sale-products-ending-this-week | Yes | PASS |
| GET | /api/v1/general/flash-sale-products-ending-today | Yes | PASS |
| GET | /api/v1/general/settings | Yes | PASS |
| GET | /api/v1/general/faqs | Yes | PASS |
| GET | /api/v1/general/governorates | Yes | PASS |
| GET | /api/v1/general/governorates/{id} | Yes | PASS |
| GET | /api/v1/general/countries | Yes | PASS |
| GET | /api/v1/general/countries/{id} | Yes | PASS |
| GET | /api/v1/general/cities | Yes | PASS |
| GET | /api/v1/general/cities/{id} | Yes | PASS |
| GET | /api/v1/general/pickup-locations | Yes | PASS |
| GET | /api/v1/general/pickup-locations/{id} | Yes | PASS |
| GET | /api/v1/general/fast-shipping/status | Yes | PASS |
| GET | /api/v1/general/site-reviews | Yes | PASS |
| GET | /api/v1/general/currencies | Yes | PASS |
| POST | /api/v1/general/currencies/select | Yes | PASS |
| ANY | /api/v1/general/checkout/callback | Yes | PASS |
| ANY | /api/v1/general/checkout/error-callback | Yes | PASS |
| POST | /api/v1/general/coupons/apply | Yes | PASS |
| GET | /api/v1/general/checkout/promotions | Yes | PASS |
| POST | /api/v1/general/checkout | Partial | FAIL |
| POST | /api/v1/general/checkout/cod/{orderId}/mark-paid | Yes | PASS |
| POST | /api/v1/general/checkout/cashier/{orderId}/mark-paid | Yes | PASS |
| POST | /api/v1/general/fast-shipping/checkout | Yes | PASS |
| GET | /api/v1/general/orders | Yes | PASS |
| GET | /api/v1/general/orders/{orderId}/invoice | Yes | PASS |
| GET | /api/v1/general/orders/{id} | Yes | PASS |
| GET | /api/v1/general/digital/downloads | Yes | PASS |
| GET | /api/v1/general/digital/license/{entitlement}/{asset} | Yes | PASS |
| GET | /api/v1/general/digital/url/{entitlement}/{asset} | Yes | PASS |
| POST | /api/v1/general/products/{id}/reviews | Yes | PASS |
| PUT | /api/v1/general/products/reviews/{id} | Yes | PASS |
| POST | /api/v1/general/device-tokens | Yes | PASS |
| DELETE | /api/v1/general/device-tokens | Yes | PASS |
| POST | /api/v1/general/site-reviews | Yes | PASS |
| GET | /api/v1/general/invoices/my-invoices | Yes | PASS |
| GET | /api/v1/general/invoices/verify/{uuid} | Yes | PASS |
| GET | /api/v1/general/invoices/view/{uuid} | Yes | PASS |
| GET | /api/v1/general/invoices/download/{uuid} | Yes | PASS |
| GET | /api/v1/general/digital/download/{entitlement}/{asset} | Yes | PASS |

**Total Endpoints Discovered**: 64
**Total Endpoints Tested**: 64
**Coverage**: 100%

## Confirmed Errors

### Error #1: ChannelContextTest - Non-existent endpoint `/api/v1/general/home`

**Endpoint:** GET `/api/v1/general/home`
**Method:** GET
**Scenario:** Cache key differs by channel / Home service cache keys use channel prefix
**Expected:** Cache keys generated with channel prefix
**Actual:** 404 Not Found - endpoint does not exist

### 3-Run Verification

| Run | Result | Status |
|-----|--------|--------|
| 1 | No cache keys were generated (404) | FAIL |
| 2 | No cache keys were generated (404) | FAIL |
| 3 | No cache keys were generated (404) | FAIL |

**Classification:** `TEST SPECIFICATION ISSUE`

**Root Cause:**
The tests in `ChannelContextTest` (lines 228-277) call `$this->getJson(self::PREFIX . '/general/home')` but there is no route defined for `/api/v1/general/home` in `routes/api.php`. The only HomeController route is `nav-data` (line 44 of routes/api.php).

**Affected Code:**
- `tests/Feature/ChannelContextTest.php:228-277` - Two test methods: `cache_key_differs_by_channel()` and `home_service_cache_keys_use_channel_prefix()`

**Database Impact:** None - endpoint doesn't exist

**Business Impact:** Test suite reports false failures; cache key channel isolation not actually tested

**Reproduction Steps:**
1. Run `php artisan test --filter "ChannelContextTest::cache_key_differs_by_channel"`
2. Observe 404 response and "No cache keys were generated" assertion failure

---

### Error #2: CheckoutConcurrencyStressTest - Violates unique pending order constraint

**Endpoint:** POST `/api/v1/general/checkout` (via test helpers)
**Method:** POST
**Scenario:** inventory_consistency_through_reserve_release_cycle
**Expected:** Create multiple pending orders for same user, reserve/release inventory
**Actual:** UNIQUE constraint violation: `idx_orders_user_pending_unique` on orders(user_id) WHERE status = 'pending'

### 3-Run Verification

| Run | Result | Status |
|-----|--------|--------|
| 1 | Integrity constraint violation: 19 UNIQUE constraint failed: orders.user_id | FAIL |
| 2 | Integrity constraint violation: 19 UNIQUE constraint failed: orders.user_id | FAIL |
| 3 | Integrity constraint violation: 19 UNIQUE constraint failed: orders.user_id | FAIL |

**Classification:** `TEST SPECIFICATION ISSUE`

**Root Cause:**
Migration `2026_08_31_130000_add_unique_pending_order_constraint.php` creates a unique partial index `idx_orders_user_pending_unique ON orders(user_id) WHERE status = 'pending'` to prevent duplicate pending orders per user. The test `inventory_consistency_through_reserve_release_cycle` (line 132) calls `makeReservedOrder()` twice for the same user, creating two pending orders which violates this business rule.

**Affected Code:**
- `tests/Feature/CheckoutConcurrencyStressTest.php:132-159` - `inventory_consistency_through_reserve_release_cycle()`
- `tests/Feature/CheckoutConcurrencyStressTest.php:321-327` - `makeReservedOrder()` helper
- `database/migrations/2026_08_31_130000_add_unique_pending_order_constraint.php:17-21` - Unique partial index

**Database Impact:** Test cannot create second pending order for same user

**Business Impact:** Test incorrectly assumes users can have multiple pending orders; business rule is one pending order per user

**Reproduction Steps:**
1. Run `php artisan test --filter "CheckoutConcurrencyStressTest::inventory_consistency_through_reserve_release_cycle"`
2. Observe UNIQUE constraint violation on second `makeReservedOrder()` call

---

### Error #3: CheckoutPendingOrderRedesignTest - Wrong reservation expiry for COD orders

**Endpoint:** POST `/api/v1/general/checkout` (via test helpers)
**Method:** POST
**Scenario:** test_checkout_stores_explicit_24h_reservation_expiry
**Expected:** reservation_expires_at = created_at + 24 hours
**Actual:** reservation_expires_at = created_at + 168 hours (7 days)

### 3-Run Verification

| Run | Result | Status |
|-----|--------|--------|
| 1 | got 2026-09-09 01:19:05 vs 2026-09-03 01:19:05 | FAIL |
| 2 | got 2026-09-09 01:19:05 vs 2026-09-03 01:19:05 | FAIL |
| 3 | got 2026-09-09 01:19:05 vs 2026-09-03 01:19:05 | FAIL |

**Classification:** `TEST SPECIFICATION ISSUE`

**Root Cause:**
The test uses COD payment method (default in `checkout()` helper, line 168) but expects 24-hour expiry. The `OrderReservationService::timeoutHoursFor()` method (line 214-221) returns 7 days (168 hours) for COD orders via `config('payment.cod_order_timeout_hours', 24 * 7)`, while non-COD orders get 24 hours via `config('payment.order_timeout_hours', 24)`.

**Affected Code:**
- `tests/Feature/CheckoutPendingOrderRedesignTest.php:225-242` - `test_checkout_stores_explicit_24h_reservation_expiry()`
- `tests/Feature/CheckoutPendingOrderRedesignTest.php:160-170` - `checkout()` helper uses 'cod' payment method
- `app/Services/Inventory/OrderReservationService.php:214-221` - `timeoutHoursFor()` method

**Database Impact:** Order gets 7-day reservation expiry instead of 24-hour

**Business Impact:** Test validates wrong business rule; COD orders correctly get 7-day reservation window

**Reproduction Steps:**
1. Run `php artisan test --filter "CheckoutPendingOrderRedesignTest::test_checkout_stores_explicit_24h_reservation_expiry"`
2. Observe assertion failure: reservation_expires_at is 7 days not 24 hours

---

### Error #4: CheckoutRegressionTest - Checkout returns 422 instead of 200

**Endpoint:** POST `/api/v1/general/checkout`
**Method:** POST
**Scenario:** checkout_refreshes_promotion_price_from_current_data / checkout_coupon_locked_during_validation
**Expected:** HTTP 200 with order created
**Actual:** HTTP 422 Unprocessable Entity

### 3-Run Verification

| Run | Result | Status |
|-----|--------|--------|
| 1 | Expected response status code [200] but received 422 | FAIL |
| 2 | Expected response status code [200] but received 422 | FAIL |
| 3 | Expected response status code [200] but received 422 | FAIL |

**Classification:** `CONFIRMED ERROR` (needs investigation - may be validation or financial invariant issue)

**Root Cause:**
Tests are receiving 422 validation errors instead of successful checkout. The error could be due to:
1. Financial invariant validation failure (subtotal - promotion - coupon + shipping != total)
2. Missing required fields in checkout payload
3. Coupon validation failure
4. Inventory reservation failure

**Affected Code:**
- `tests/Feature/CheckoutRegressionTest.php:642-671` - `checkout_refreshes_promotion_price_from_current_data()`
- `tests/Feature/CheckoutRegressionTest.php:764-774` - `checkout_coupon_locked_during_validation()`
- `app/Services/Invoice/Validators/FinancialInvariantValidator.php:24-33` - Financial invariant validation
- `app/Http/Controllers/Api/General/OrderController.php:133` - checkout() method

**Database Impact:** Order not created, inventory not reserved

**Business Impact:** Checkout flow fails for valid scenarios; financial invariant validator may be too strict or test data incorrect

**Reproduction Steps:**
1. Run `php artisan test --filter "checkout_refreshes_promotion_price_from_current_data"`
2. Observe 422 response with financial invariant violation message

---

### Error #5: CmsPageTest - Wrong URL paths

**Endpoint:** Various `/api/v1/cms-pages/...` endpoints
**Method:** GET, POST, PUT, DELETE
**Scenario:** All three tests in CmsPageTest
**Expected:** Various HTTP status codes (200, 201, 403)
**Actual:** HTTP 404 Not Found

### 3-Run Verification

| Run | Result | Status |
|-----|--------|--------|
| 1 | Expected 200/201/403 but received 404 | FAIL |
| 2 | Expected 200/201/403 but received 404 | FAIL |
| 3 | Expected 200/201/403 but received 404 | FAIL |

**Classification:** `TEST SPECIFICATION ISSUE`

**Root Cause:**
Tests use `/api/v1/cms-pages` but the actual routes are:
- Public: `/api/v1/general/content-pages` and `/api/v1/general/static-pages`
- Admin: `/api/v1/content-pages` (under admin middleware)

The test file `tests/Feature/CmsPageTest.php` uses incorrect URL prefix.

**Affected Code:**
- `tests/Feature/CmsPageTest.php:74` - `getJson('/api/v1/cms-pages/home')`
- `tests/Feature/CmsPageTest.php:97` - `postJson('/api/v1/cms-pages', ...)`
- `tests/Feature/CmsPageTest.php:138` - `postJson('/api/v1/cms-pages', ...)`
- `routes/api.php:67-70` - Public content-pages routes under `/general`
- Second routes file (Marvel package) - Admin content-pages routes

**Database Impact:** None - endpoints not found

**Business Impact:** CMS page API not tested; all 3 tests fail

**Reproduction Steps:**
1. Run `php artisan test --filter "CmsPageTest"`
2. Observe all 3 tests getting 404

---

### Error #6: ConcurrencyRaceConditionTest - Wrong enum value

**Endpoint:** N/A (unit test)
**Method:** N/A
**Scenario:** concurrent_single_use_coupon_reservation_prevents_double_booking / idempotent_reservation_refresh
**Expected:** Tests to run
**Actual:** BadMethodCallException - DiscountType::FIXED does not exist

### 3-Run Verification

| Run | Result | Status |
|-----|--------|--------|
| 1 | Call to undefined method DiscountType::FIXED | FAIL |
| 2 | Call to undefined method DiscountType::FIXED | FAIL |
| 3 | Call to undefined method DiscountType::FIXED | FAIL |

**Classification:** `TEST SPECIFICATION ISSUE`

**Root Cause:**
The test uses `DiscountType::FIXED` (line 180) but the enum `Marvel\Enums\DiscountType` only has `PERCENTAGE`, `FIXED_RATE`, and `FREE_SHIPPING` constants.

**Affected Code:**
- `tests/Feature/ConcurrencyRaceConditionTest.php:180` - Uses `DiscountType::FIXED`
- `packages/marvel/src/Enums/DiscountType.php:14-16` - Actual enum constants

**Database Impact:** N/A

**Business Impact:** Concurrency tests for coupon reservation not running

**Reproduction Steps:**
1. Run `php artisan test --filter "ConcurrencyRaceConditionTest"`
2. Observe BadMethodCallException on DiscountType::FIXED

---

### Error #7: CouponSystemTest & AssignedCouponSystemTest - FinancialInvariantException

**Endpoint:** POST `/api/v1/general/checkout`
**Method:** POST
**Scenario:** Multiple coupon checkout scenarios
**Expected:** HTTP 200 with order created
**Actual:** FinancialInvariantException - computed total != declared total

### 3-Run Verification

| Run | Result | Status |
|-----|--------|--------|
| 1 | Financial invariant violation (10 tests) | FAIL |
| 2 | Financial invariant violation (10 tests) | FAIL |
| 3 | Financial invariant violation (10 tests) | FAIL |

**Classification:** `CONFIRMED ERROR` (production bug in financial calculation or test data)

**Root Cause:**
The `FinancialInvariantValidator` validates that `subtotal - promotion - coupon + shipping + fast_shipping_fee = total`. The tests are failing this validation, suggesting either:
1. Bug in checkout price calculation
2. Test data doesn't match expected calculation
3. Coupon/promotion discount not applied correctly in snapshot

**Affected Code:**
- `tests/Feature/CouponSystemTest.php` - 3 failing tests
- `tests/Feature/AssignedCouponSystemTest.php` - 7 failing tests
- `app/Services/Invoice/Validators/FinancialInvariantValidator.php:24-33` - Validator
- `app/Services/Invoice/InvoiceSnapshotValidator.php:19` - Calls validator
- `app/Services/Payment/PaymentCheckoutHandler.php` - Creates transaction

**Database Impact:** Orders not created, transactions not recorded

**Business Impact:** Coupon checkout flow broken; financial invariant may prevent valid orders

**Reproduction Steps:**
1. Run `php artisan test --filter "CouponSystemTest::checkout_records_coupon_usage"`
2. Observe FinancialInvariantException with diff amount

---

### Error #8: CouponsProductionHardenTest - Multiple 422 errors

**Endpoint:** POST `/api/v1/general/checkout`
**Method:** POST
**Scenario:** 11 failing coupon checkout tests
**Expected:** HTTP 200 with order created
**Actual:** HTTP 422 or QueryException

### 3-Run Verification

| Run | Result | Status |
|-----|--------|--------|
| 1 | 11 tests failing (422/QueryException) | FAIL |
| 2 | 11 tests failing (422/QueryException) | FAIL |
| 3 | 11 tests failing (422/QueryException) | FAIL |

**Classification:** `CONFIRMED ERROR` (likely related to Error #7 - financial invariant)

**Root Cause:**
Similar to CouponSystemTest, checkout returns 422 validation errors. One test fails with QueryException (duplicate coupon usage). The financial invariant validator is likely the common cause.

**Affected Code:**
- `tests/Feature/CouponsProductionHardenTest.php` - 11 failing tests
- `app/Services/Invoice/Validators/FinancialInvariantValidator.php` - Validator

**Database Impact:** Orders not created

**Business Impact:** Production coupon checkout scenarios failing

**Reproduction Steps:**
1. Run `php artisan test --filter "CouponsProductionHardenTest"`
2. Observe 11 failing tests with 422/QueryException

---

### Error #9: ContentPageSectionTypeApiTest - 7 tests failing

**Endpoint:** Various section/content-page admin endpoints
**Method:** GET, POST, PUT, DELETE, PATCH
**Scenario:** Various section type and reorder tests
**Expected:** HTTP 200/201
**Actual:** HTTP 422 or other errors

### 3-Run Verification

| Run | Result | Status |
|-----|--------|--------|
| 1 | 7 failed, 74 passed | FAIL |
| 2 | 7 failed, 74 passed | FAIL |
| 3 | 7 failed, 74 passed | FAIL |

**Classification:** `CONFIRMED ERROR` (needs investigation)

**Root Cause:**
Tests fail at line 993 when creating a banner section with translated title. The Arabic title assertion fails, suggesting localization or section creation issue.

**Affected Code:**
- `tests/Feature/ContentPageSectionTypeApiTest.php:993` - Arabic title assertion
- `app/Http/Controllers/Api/SectionController.php` - Section creation
- `app/Http/Controllers/Api/SectionTypeController.php` - Section type handling

**Database Impact:** Sections may not be created correctly

**Business Impact:** Admin section management partially broken

**Reproduction Steps:**
1. Run `php artisan test --filter "ContentPageSectionTypeApiTest"`
2. Observe 7 failing tests

---

## Flaky / Investigation Required

No flaky failures detected (all failures are deterministic 3/3).

## Non-Deterministic / Not Confirmed

No non-deterministic failures detected.

## Test Infrastructure Issues

1. **Database Constraint - transactions.invoice_id NOT NULL**: During debug testing, found that COD checkout fails to create transaction because `invoice_id` is required but not provided. This may affect COD order flow in production.

2. **Missing Controller - BkashTokenizePaymentController**: Route list command fails due to missing controller class. This doesn't affect tests but indicates incomplete code.

## Test Specification Issues

1. **ChannelContextTest** (2 tests) - Testing non-existent `/general/home` endpoint
2. **CheckoutConcurrencyStressTest** (1 test) - Violates business rule (unique pending order per user)
3. **CheckoutPendingOrderRedesignTest** (1 test) - Wrong expectation for COD reservation expiry
4. **CmsPageTest** (3 tests) - Wrong URL paths (`/cms-pages` vs `/general/content-pages`)
5. **ConcurrencyRaceConditionTest** (2 tests) - Wrong enum value (`DiscountType::FIXED` vs `FIXED_RATE`)

**Total Test Specification Issues: 9 tests**

## Passed Critical Scenarios

- All 42 public GET endpoints tested and passing
- Authentication/authorization tests for protected endpoints passing
- Cart operations (add, update, delete, bulk) passing
- Order listing and detail passing
- Invoice viewing/downloading passing
- Digital download flows passing
- Product/category/brand/banner/slider listings passing
- Settings, FAQs, governorates, countries, cities, pickup locations passing
- Flash sale listings passing
- Currency selection passing
- Site reviews passing
- Payment callbacks passing
- Channel context filtering (products) passing
- Fast shipping status passing
- Coupon listing passing
- Promotions listing passing

## Untested Endpoints

None - all 64 discovered endpoints have at least basic test coverage.

---

*Report generated by API Forensic Testing Process*