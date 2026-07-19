# Product Module — Bug Report

## BUG-PRD-001: `updateProduct()` method visibility inconsistency

**Severity:** Low
**Status:** Open
**File:** `packages/marvel/src/Http/Controllers/ProductController.php:324`

**Issue:** `updateProduct()` is `public` but it is an internal helper only called from `update()`. Should be `private` to match the pattern used in Brand (`brandUpdate`), Category (`categoryUpdate`), and Attribute (`updateAttribute`).

**Current:**
```php
public function updateProduct(Request $request): mixed
```

**Expected:**
```php
private function updateProduct(Request $request): mixed
```

---

## BUG-PRD-002: `ProductStore()` method visibility inconsistency

**Severity:** Low
**Status:** Open
**File:** `packages/marvel/src/Http/Controllers/ProductController.php:249`

**Issue:** `ProductStore()` is `public` but is an internal helper only called from `store()`. Should be `private`.

**Current:**
```php
public function ProductStore(Request $request): mixed
```

**Expected:**
```php
private function ProductStore(Request $request): mixed
```

---

## BUG-PRD-003: `destroyProduct()` method visibility inconsistency

**Severity:** Low
**Status:** Open
**File:** `packages/marvel/src/Http/Controllers/ProductController.php:351`

**Issue:** `destroyProduct()` is `public` but is an internal helper only called from `destroy()`. Should be `private`.

**Current:**
```php
public function destroyProduct(Request $request): JsonResponse
```

**Expected:**
```php
private function destroyProduct(Request $request): JsonResponse
```

---

## BUG-PRD-004: Missing DB transaction in `updateProduct()` repository method

**Severity:** High
**Status:** Open
**File:** `packages/marvel/src/Database/Repositories/ProductRepository.php:116`

**Issue:** `storeProduct()` wraps everything in a DB transaction (`beginTransaction`/`commit`/`rollBack`), but `updateProduct()` does not. This means partial failures during variant deletion, image updates, or relation syncs can leave the database in an inconsistent state.

**Current:**
```php
public function updateProduct(Request $request, $id)
{
    // No DB::beginTransaction()
    ...
    // Multiple writes: delete variants, create variants, update images, sync relations
    return $product;
}
```

**Expected:**
```php
public function updateProduct(Request $request, $id)
{
    DB::beginTransaction();
    try {
        ...
        DB::commit();
        return $product;
    } catch (\Throwable $th) {
        DB::rollBack();
        throw $th;
    }
}
```

---

## BUG-PRD-005: Product CRUD translation keys missing in English

**Severity:** Medium
**Status:** Open
**File:** `resources/lang/en/message.php`

**Issue:** The English translation file is missing the following product CRUD keys:
- `MESSAGE.CREATE_PRODUCT_SUCCESSFULLY`
- `MESSAGE.UPDATE_PRODUCT_SUCCESSFULLY`
- `MESSAGE.DELETE_PRODUCT_SUCCESSFULLY`
- `MESSAGE.PRODUCTS_DELETED_SUCCESSFULLY`

These keys exist in Arabic but not English. When the app falls back to English, users see the key string instead of a readable message.

**Expected:** Add English translations:
```php
'MESSAGE.CREATE_PRODUCT_SUCCESSFULLY' => 'Product created successfully',
'MESSAGE.UPDATE_PRODUCT_SUCCESSFULLY' => 'Product updated successfully',
'MESSAGE.DELETE_PRODUCT_SUCCESSFULLY' => 'Product deleted successfully',
'MESSAGE.PRODUCTS_DELETED_SUCCESSFULLY' => 'Products deleted successfully',
```

---

## BUG-PRD-006: `ProductStore()` and `updateProduct()` accept generic `Request` instead of typed FormRequest

**Severity:** Low
**Status:** Open
**File:** `packages/marvel/src/Http/Controllers/ProductController.php:249,324`

**Issue:** After validation passes in `store()`/`update()`, the helper methods `ProductStore()` and `updateProduct()` type-hint `Request` instead of their respective `ProductCreateRequest`/`ProductUpdateRequest`. This loses type safety.

**Current:**
```php
public function ProductStore(Request $request): mixed
public function updateProduct(Request $request): mixed
```

**Expected:**
```php
private function ProductStore(ProductCreateRequest $request): mixed
private function updateProduct(ProductUpdateRequest $request): mixed
```

---

## BUG-PRD-007: No `deleteAttribute` method for product (inconsistent with brand/attribute pattern)

**Severity:** Low
**Status:** Won't Fix (current inline approach works)

Product's `destroy()` directly calls `$this->repository->findOrFail($id)->delete()` inline. Brand and Attribute have separate helper methods. This is inconsistent but functional.
