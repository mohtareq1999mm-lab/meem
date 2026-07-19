# Product Module — Backend JIRA Tasks

## B-001: Make internal helper methods private

**Priority:** Low
**Story Points:** 1
**Labels:** refactoring, consistency
**File:** `ProductController.php`

**Description:** `ProductStore()`, `updateProduct()`, and `destroyProduct()` are all `public` but are internal helpers only called from controller methods. Make them `private` to match the Brand/Category/Attribute pattern.

**Acceptance Criteria:**
- `ProductStore()` → `private`
- `updateProduct()` → `private`
- `destroyProduct()` → `private`

---

## B-002: Wrap `updateProduct()` repository method in DB transaction

**Priority:** High
**Story Points:** 2
**Labels:** bug, data-integrity
**File:** `ProductRepository.php`

**Description:** `updateProduct()` does not use a DB transaction, while `storeProduct()` does. Multiple writes happen (delete variants, create variants, update images, sync relations). A partial failure can corrupt data.

**Acceptance Criteria:**
- `DB::beginTransaction()` at start
- `DB::commit()` after all operations
- `DB::rollBack()` in catch block
- All operations inside try/catch

---

## B-003: Add missing English translation keys

**Priority:** Medium
**Story Points:** 1
**Labels:** bug, i18n
**File:** `resources/lang/en/message.php`

**Description:** Four product CRUD translation keys exist in Arabic but are missing in English. When the app falls back to English, users see the key string instead of a readable message.

**Acceptance Criteria:**
- `MESSAGE.CREATE_PRODUCT_SUCCESSFULLY` → "Product created successfully"
- `MESSAGE.UPDATE_PRODUCT_SUCCESSFULLY` → "Product updated successfully"
- `MESSAGE.DELETE_PRODUCT_SUCCESSFULLY` → "Product deleted successfully"
- `MESSAGE.PRODUCTS_DELETED_SUCCESSFULLY` → "Products deleted successfully"

---

## B-004: Type-hint helper methods with specific FormRequests

**Priority:** Low
**Story Points:** 1
**Labels:** refactoring, type-safety
**File:** `ProductController.php`

**Description:** `ProductStore()` and `updateProduct()` type-hint generic `Request`. Change to `ProductCreateRequest` and `ProductUpdateRequest` for type safety and IDE support.

**Acceptance Criteria:**
- `ProductStore(ProductCreateRequest $request)`
- `updateProduct(ProductUpdateRequest $request)`

---

## B-005: Add /products test suite

**Priority:** High
**Story Points:** 8
**Labels:** testing, coverage
**Files:** `tests/Feature/`

**Description:** There are no dedicated product CRUD test files. Create comprehensive feature tests covering:

- Guest authentication (401 for create/update/delete, 200 for index/show)
- Permission enforcement (403 for missing permissions)
- Create product (simple, variable, with variants, without values)
- Show product (by ID, by slug)
- Update product (name only, with variants)
- Delete product (single, nonexistent)
- Validation (missing required fields, invalid enums, wrong types)
- Response structure (list, show)
- Bulk delete, destroy all
- Soft delete behavior (product still exists in DB with `deleted_at`)

**Acceptance Criteria:**
- Minimum 25 test methods
- All CRUD operations covered
- Authentication and authorization covered
- Validation failures covered
- Edge cases covered (nonexistent IDs, empty data)
- Translation key assertions
