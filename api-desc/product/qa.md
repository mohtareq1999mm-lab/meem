# Product Module — QA Test Plan

## Test Categories

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
- ✅ Toggle fast shipping
- ❌ No dedicated test file exists — all CRUD tests need to be created

### 2. Validation (ProductCreateRequest / ProductUpdateRequest)
- ❌ Missing name → 422
- ❌ Missing description → 422
- ❌ Missing categories → 422
- ❌ Missing images → 422
- ❌ Invalid product_type → 422
- ❌ Invalid status → 422
- ❌ Invalid discount_type → 422
- ❌ has_discount true but missing discount_type → 422
- ❌ has_flash_sale true but missing flash_sale_id → 422
- ❌ Variants missing required fields (price, quantity, attribute_values) → 422
- ❌ Name exceeds 255 chars → 422
- ❌ Invalid image mime type → 422

### 3. Authentication & Authorization
- ❌ Guest cannot create product → 401
- ❌ Guest cannot update product → 401
- ❌ Guest cannot delete product → 401
- ❌ Guest can list products → 200 (public)
- ❌ Guest can show product → 200 (public)
- ❌ User without create-product permission → 403
- ❌ User without update-product permission → 403
- ❌ User without delete-product permission → 403

### 4. Edge Cases
- ❌ Show nonexistent product → 404
- ❌ Update nonexistent product → 404
- ❌ Delete nonexistent product → 404
- ❌ Create product with empty name array → 422
- ❌ Create product with empty values arrays → passes (valid)
- ❌ Bulk delete with empty IDs array → 422
- ❌ Bulk delete with nonexistent IDs → 422

### 5. Resource Structure
- ❌ List response contains correct pagination structure
- ❌ Show response contains all product fields
- ❌ Show response contains variants when product_type=variable
- ❌ Response message matches translation keys

### 6. Soft Delete
- ❌ Deleted product has `deleted_at` set
- ❌ Deleted product not visible in index
- ❌ Show deleted product returns 404
- ❌ Bulk delete sets deleted_at on all specified products

## Coverage Summary
- **Critical gaps:** Entire test suite is missing — no Product CRUD tests exist
- **Authentication:** Public index/show endpoints need testing
- **Validation:** 15+ validation rules need test coverage
- **Soft delete:** Complete behavior untested
- **Response structure:** JSON shape untested
