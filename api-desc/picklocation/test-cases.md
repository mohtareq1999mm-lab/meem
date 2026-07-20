# Test Cases - Pickup Location Feature

## Current Coverage

### PickupLocationTest (525 lines, 18 tests)

| Category | Tests | File |
|----------|-------|------|
| Admin CRUD | 5 | `PickupLocationTest.php` |
| Validation | 4 | `PickupLocationTest.php` |
| Authorization | 3 | `PickupLocationTest.php` |
| Public API | 3 | `PickupLocationTest.php` |
| Edge Cases | 3 | `PickupLocationTest.php` |

### PickupLocationPricingIntegrationTest (1232 lines, 40+ tests)

| Category | Tests | File |
|----------|-------|------|
| Pickup API | 8 | `PickupLocationPricingIntegrationTest.php` |
| Pricing | 7 | `PickupLocationPricingIntegrationTest.php` |
| Checkout with pickup | 11 | `PickupLocationPricingIntegrationTest.php` |
| Checkout hardening | 7 | `PickupLocationPricingIntegrationTest.php` |
| Auth/permissions | 4 | `PickupLocationPricingIntegrationTest.php` |
| Order resource | 2 | `PickupLocationPricingIntegrationTest.php` |
| Validation | 2 | `PickupLocationPricingIntegrationTest.php` |

### Test Count: ~58 tests total

## What's Covered

✅ Admin list with pagination
✅ Admin create/store with validation
✅ Admin show
✅ Admin update
✅ Admin delete (soft delete)
✅ Store requires store_name, address, phone
✅ Store validates email format
✅ Store validates display_order is integer (non-negative)
✅ Unauthenticated access blocked (401)
✅ Customer cannot create/update/delete (403)
✅ Public list returns only active
✅ Public show returns 404 for inactive
✅ 404 for non-existent ID
✅ Ordering by display_order
✅ Search by store_name
✅ Integration with checkout (pickup snapshot)
✅ Order resource includes pickup_location for pickup orders
✅ Order resource excludes pickup_location for delivery orders
✅ Working hours validation
✅ Soft-deleted pickup location preserves snapshot in existing orders

## Recommended Additional Tests

| # | Test | Priority |
|---|------|----------|
| FT-001 | `inactive=true` filter only returns inactive | Low |
| FT-002 | Both active + inactive filters together | Low |
| FT-003 | Public show returns 404 for soft-deleted | Medium |
| FT-004 | Export/import pickup locations | Low |
