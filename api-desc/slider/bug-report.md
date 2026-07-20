# Bug Report — Slider Module

---

## BUG-SL-001: Missing `sliders` Table Migration

**Severity:** High

**Component:** Database

**Description:** The `sliders` table migration does not exist in the production migration directory. The table schema is only defined in `tests/Concerns/CreatesTestTables.php` for test purposes. Running `php artisan migrate` in production will not create the `sliders` table.

**Code Location:** `database/migrations/` — missing

**Status:** ❌ Open — A migration file must be created.

---

## BUG-SL-002: Duplicate Route Registrations

**Severity:** Low

**Component:** Routes

**Description:** `Route::apiResource('sliders', SliderController::class)` is registered 3 times in `packages/marvel/src/Rest/Routes.php` (lines 164, 165, 204). This does not cause errors (Laravel deduplicates routes by URI + method), but it is technical debt.

**Code Location:** `packages/marvel/src/Rest/Routes.php` — lines 164, 165, 204

**Status:** ❌ Open — Should be consolidated to a single registration.

---

## BUG-SL-003: Inconsistent Media Collection Names

**Severity:** Low

**Component:** Repository

**Description:** The `createSlider` method uploads to `slider-image-desktop` and `slider-image-mobile` collections, while `updateSlider` uploads to `sliders-desktop` and `sliders-mobile` collections. This inconsistency could cause issues when retrieving images after update.

**Code Location:** `packages/marvel/src/Database/Repositories/SliderRepository.php` — `createSlider()` vs `updateSlider()`

**Status:** ❌ Open — Collections should be unified.

---

## BUG-SL-004: MediaCleanupObserver Skips Cleanup on Soft Delete

**Severity:** Low

**Component:** Observer

**Description:** The `MediaCleanupObserver::deleting()` method checks for `SoftDeletes` trait and returns early if found. Since `Slider` uses `SoftDeletes`, media files are NOT cleaned up on soft delete. They are only cleaned up on `forceDeleting`. This means soft-deleted sliders retain their media files indefinitely.

**Code Location:** `app/Observers/MediaCleanupObserver.php`

**Status:** ✅ By design — Media is preserved for potential restore. Cleanup happens on force delete.
