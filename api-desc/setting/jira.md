# Settings Module — Backend Jira Tasks

---

## Task 1: Cache Public Settings Endpoint

**Priority:** Medium
**Component:** SettingService
**Effort:** Small
**Files:**
- `app/Services/General/SettingService.php`

**Description:** `SettingService::getSetting()` calls `Settings::first()` on every request. Add `Cache::remember()` with 600s TTL.

**Status:** Done — both the admin and public settings reads are now cached under the `settings` tag via `HasCache::remember` (TTL 4h) and flushed on update.

---

## Task 5: Expose `currency_selection_enabled` in Settings

**Priority:** High
**Component:** Settings
**Effort:** Small
**Files:**
- `packages/marvel/src/Http/Resources/SettingResource.php`
- `packages/marvel/src/Http/Requests/SettingsRequest.php`
- `packages/marvel/src/Http/Controllers/SettingsController.php`
- `database/seeders/SettingSeeder.php`

**Description:** Manage the storefront currency-selection toggle through the settings API.

**Acceptance Criteria:**
- [ ] `SettingResource` returns top-level `currency_selection_enabled` boolean (admin + public)
- [ ] `SettingsRequest` accepts `currency_selection_enabled => sometimes|boolean`
- [ ] `SettingsController::update()` merges the flag into `options` without dropping other keys, resets the effective-currency memo, and flushes the `settings` tag
- [ ] Seeder defaults the flag to `false`

**Status:** DONE (2026-08-12).

---

## Task 2: Write Tests for PUT /settings

**Priority:** High
**Component:** Tests
**Effort:** Medium
**Files:**
- `tests/Feature/Settings/`

**Description:** Admin settings update lacks feature tests.

**Acceptance Criteria:**
- [ ] Test PUT with valid data returns 200
- [ ] Test PUT without auth returns 401
- [ ] Test PUT without permission returns 403
- [ ] Test PUT with invalid data returns 422
- [ ] Test minimumOrderAmount updates correctly

---

## Task 3: Write Tests for Fast Shipping Endpoints

**Priority:** Medium
**Component:** Tests
**Effort:** Medium
**Files:**
- `tests/Feature/Settings/`

**Description:** Fast shipping GET/PUT endpoints lack feature tests.

**Acceptance Criteria:**
- [ ] Test GET returns settings with defaults
- [ ] Test PUT updates and returns 200
- [ ] Test cache invalidation after update
- [ ] Test validation rules (duration_minutes max 1440, fee min 0, date_format)

---

## Task 4: Validate minimumOrderAmount on Checkout

**Status:** DONE

**Description:** `CheckoutRepository::verify()` already enforces minimum order amount (line 61-63). The value is read from `settings.options.minimumOrderAmount` (line 39).
