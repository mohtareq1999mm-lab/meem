# Product Module — QA Test Plan

## Test File: `ProductCrudTest.php` (62 tests, all passing)

### 1. Functionality
- ✅ Create simple product with all required fields
- ✅ Create variable product with variants
- ✅ Create product without variants (auto-detects as simple)
- ✅ List products with pagination
- ✅ Show product by ID
- ✅ Show product by slug
- ✅ Update product name only
- ✅ Update product pricing
- ✅ Update product with new variants (old variants deleted)
- ✅ Delete product (soft delete)
- ✅ Bulk delete products
- ✅ Destroy all products
- ✅ List reviews by product
- ✅ Show review by ID
- ✅ Toggle review approval
- ✅ Delete review
- ✅ Import status, cancel, download-errors

### 2. Validation (ProductCreateRequest / ProductUpdateRequest)
- ✅ Missing name → 422
- ✅ Missing description → 422
- ✅ Missing categories → 422
- ✅ Missing images → 422
- ✅ Invalid product_type → 422
- ✅ Invalid status → 422
- ✅ Missing in_stock → 422
- ✅ Missing has_discount → 422
- ✅ Missing has_flash_sale → 422
- ❌ has_discount true but missing discount_type → 422
- ❌ has_flash_sale true but missing flash_sale_id → 422
- ❌ Variants missing required fields (price, quantity, attribute_values) → 422
- ❌ Name exceeds 255 chars → 422

### 3. Authentication & Authorization
- ✅ Guest cannot create product → 401
- ✅ Guest cannot update product → 401
- ✅ Guest cannot delete product → 401
- ✅ Guest cannot create review → 401
- ✅ Guest cannot update review → 401
- ✅ Guest cannot delete review → 401
- ✅ Guest cannot toggle-approve → 401
- ✅ Guest cannot bulk-delete → 401
- ✅ Guest cannot delete-all → 401
- ✅ Guest cannot import → 401
- ✅ Guest cannot get import status → 401
- ✅ Guest cannot cancel import → 401
- ✅ Guest cannot download import errors → 401
- ✅ User without create-product permission → 403
- ✅ User without update-product permission → 403
- ✅ User without delete-product permission → 403

### 4. Edge Cases
- ✅ Show nonexistent product → 404
- ✅ Update nonexistent product → 404
- ✅ Delete nonexistent product → 404
- ✅ Show nonexistent review → 404
- ✅ Toggle-approve nonexistent review → error
- ✅ Delete nonexistent review → 404
- ✅ Import status nonexistent → 404
- ✅ Cancel import nonexistent → 404
- ✅ Download errors nonexistent → 404
- ✅ Bulk delete with empty IDs array → 422

### 5. Resource Structure
- ✅ List response contains correct structure
- ✅ Show response contains all product fields
- ❌ Show response contains variants when product_type=variable
- ✅ Response message matches translation keys

### 6. Soft Delete
- ✅ Deleted product has `deleted_at` set
- ❌ Deleted product not visible in index
- ❌ Show deleted product returns 404

## Coverage Summary
- **File:** `tests/Feature/ProductCrudTest.php`
- **62 tests, all passing** (out of 139 total product+attribute tests)
- **Covers:** Products CRUD + reviews CRUD + import routes
- **Gaps:** Discount/flash sale validation details, variant-specific validation, deleted product not in index
