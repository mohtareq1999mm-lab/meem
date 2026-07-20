# Bug Report - Order Feature

## Issue 1: Dual Model System

- **Description:** Two separate Order model schemas coexist. Marvel package uses legacy columns (`tracking_number`, `order_status`, `payment_status`, `amount`, `total`, `paid_total`). App layer uses modern columns (`status`, `price`, `total_price`, `promotion_id`). The `syncOrderStatusColumn()` method bridges the gap but is a potential inconsistency point.
- **Impact:** Medium — status changes must be synced explicitly or modern/legacy columns can diverge.

## Issue 2: Commented Routes

- **File:** `packages/marvel/src/Rest/Routes.php`
- **Description:** Standard `apiResource('orders')` routes are commented out. Only specific routes explicitly defined.
- **Impact:** Low — suggests incomplete migration from old Marvel routing.

## Issue 3: No Base Migration

- **Description:** No `create_orders_table` migration found. Only modification migrations exist.
- **Impact:** Low — fresh installations may fail.

## Issue 4: Duplicate Route Definitions

- **File:** `routes/api.php` lines 39-45 and 84-90
- **Description:** Checkout routes (`checkout/promotions`, `checkout`, `checkout/cod/{orderId}/mark-paid`, etc.) defined twice. Harmless but indicates copy-paste.
- **Impact:** Low.

## Issue 5: Missing English/Arabic Translations

- **Description:** Only German (`de`) order translation file exists in `resources/lang/`. English and Arabic translation files for order notifications are missing.
- **Impact:** Medium — order notification texts will show key strings for EN/AR locales.
