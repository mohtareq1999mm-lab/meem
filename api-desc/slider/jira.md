# Slider Module — Backend Jira Tasks

## Task 1: Add Missing `sliders` Table Migration

**Component:** Database
**Status:** ❌ Open

**Description:** The `sliders` table migration does not exist in the project's migration directory. The schema is only defined in `tests/Concerns/CreatesTestTables.php`. Create a proper migration file for the `sliders` table with columns: id, title (text), slug (string), order (integer), status (boolean), softDeletes, timestamps.

---

## Task 2: Deduplicate Route Registrations

**Component:** Routes
**File:** `packages/marvel/src/Rest/Routes.php`
**Status:** ❌ Open

**Description:** `Route::apiResource('sliders', SliderController::class)` is registered 3 times (lines 164, 165, 204). Consolidate to a single registration and ensure all middleware/permissions are correctly applied.

---

## Task 3: Unify Media Collection Names

**Component:** Repository
**File:** `packages/marvel/src/Database/Repositories/SliderRepository.php`
**Status:** ❌ Open

**Description:** Create uses `slider-image-desktop` / `slider-image-mobile` collections while update uses `sliders-desktop` / `sliders-mobile`. Unify to a single naming convention.

---

## Task 4: Run Full Slider Test Suite

**Component:** Tests
**File:** `tests/Feature/SliderApiTest.php`
**Status:** ✅ Done

**Description:** Comprehensive test suite with ~47 tests covering CRUD, permissions, validation, soft deletes, status toggle, reorder, product associations, translations, and response structure.

---

## Task 5: Verify Public API Response Consistency

**Component:** Public Controller
**Status:** ❌ Open

**Description:** Verify public slider endpoints return consistent responses. On `index`, title should be translated string. On `getSliderBySlug`, title should have the same format. Check channel filter works correctly for multi-channel setups.

---

## Task 6: Add Slider-Product Pivot Validation

**Component:** Repository
**Status:** ❌ Open

**Description:** Currently, product IDs are synced without validating that the products exist or belong to the same shop. Add validation to ensure product IDs are valid before syncing.
