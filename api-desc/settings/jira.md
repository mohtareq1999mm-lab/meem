# Settings Module — Jira Tasks

## Task 1: Add missing `Settings::getData()` static method

**Priority:** Critical
**Component:** Settings Model
**Effort:** Small
**Files:**
- `packages/marvel/src/Database/Models/Settings.php`

**Description:** The `Settings::getData($language)` static method is called from 19 locations across the codebase but was never defined on the Settings model. Add the method with cache support using key pattern `cached_settings_{language}` with 24-hour TTL.

**Acceptance Criteria:**
- [ ] `Settings::getData()` returns the first settings record (or null)
- [ ] `Settings::getData('en')` accepts optional language parameter
- [ ] Result is cached for 86400 seconds
- [ ] Different languages use different cache keys
- [ ] Null results are not cached

---

## Task 2: Fix `store()` method spread operator bug

**Priority:** High
**Component:** SettingsController
**Effort:** Small
**Files:**
- `packages/marvel/src/Http/Controllers/SettingsController.php`

**Description:** The `store()` method spreads `$this->repository->getApplicationSettings()` as if it were an array, but it returns a Settings model instance. In PHP 8.1+, spreading a non-Traversable object throws a fatal error. Additionally, the spread order is reversed — old settings override new values.

**Acceptance Criteria:**
- [ ] `$existingOptions` is extracted from the model's `options` attribute
- [ ] New options override existing options (not vice versa)
- [ ] No PHP fatal error when spreading settings
- [ ] `server_info` is always appended

---

## Task 3: Fix misleading error messages in `updateSetting()`

**Priority:** Medium
**Component:** SettingsRepository
**Effort:** Small
**Files:**
- `packages/marvel/src/Database/Repositories/SettingsRepository.php`

**Description:** Two issues: (1) Favicon upload failure says "Logo upload failed" instead of "Favicon upload failed". (2) The generic catch block always says "Logo upload failed" even for database errors.

**Acceptance Criteria:**
- [ ] Favicon failure shows "Favicon upload failed, please check the file format or size."
- [ ] Generic exception shows "Settings update failed, please try again."
- [ ] `HttpException` from image upload is re-thrown directly (not wrapped)

---

## Task 4: Make `update()` `$id` parameter optional

**Priority:** Medium
**Component:** SettingsController
**Effort:** Trivial
**Files:**
- `packages/marvel/src/Http/Controllers/SettingsController.php`

**Description:** The public PUT route at `routes.php:135` (`Route::put('settings', ...)`) has no `{id}` parameter, but the controller method `update($id)` requires it. Makes the `$id` parameter optional (`$id = null`) since it's ignored anyway (method always uses `$this->repository->first()`).

**Acceptance Criteria:**
- [ ] `PUT /api/v1/settings` (without ID) works
- [ ] `PUT /api/v1/settings/{id}` (with ID) also works
- [ ] Both routes update the same (first) settings record

---

## Task 5: Add test suite for Settings module

**Priority:** High
**Component:** Tests
**Effort:** Medium
**Files:**
- `tests/Feature/Settings/SettingsCrudTest.php`
- `tests/Feature/Settings/SettingsValidationTest.php`
- `tests/Feature/Settings/SettingsAuthenticationTest.php`
- `tests/Feature/Settings/SettingsRegressionTest.php`

**Description:** No existing tests for the Settings module. Create comprehensive test suite covering: CRUD operations, validation, authentication/authorization, and regression tests for `getData()` caching behavior.

**Acceptance Criteria:**
- [ ] 22 tests covering CRUD, validation, auth, and regression
- [ ] All tests pass
- [ ] Tests cover caching behavior of `getData()`
- [ ] Tests cover public GET access

---

## Task 6: Clean up `show()` method (done)

**Priority:** Low
**Component:** SettingsController
**Effort:** Trivial
**Files:**
- `packages/marvel/src/Http/Controllers/SettingsController.php`

**Description:** Removed unused `$id` parameter from `show()` (settings is singleton, only one row). Changed to use `apiResponse()` with `SettingResource::make()` for consistency with `index()`.

**Status:** ✅ Completed

---

## Task 7: Remove duplicate route definition

**Priority:** Low
**Component:** Routes
**Effort:** Trivial
**Files:**
- `packages/marvel/src/Rest/Routes.php`

**Description:** Line 244 registers `Route::apiResource('settings', SettingsController::class, ['only' => ['index']])` which creates a duplicate `GET /api/v1/settings` route. Line 134 already registers the same route. The duplicate should be removed.

**Acceptance Criteria:**
- [ ] Only one `GET /api/v1/settings` route definition exists
- [ ] Behavior unchanged
