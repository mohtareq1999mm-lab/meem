# Pages Module — Backend Jira Tasks

---

## Task 1: Implement Caching for Public Endpoints

**Priority:** Medium
**Component:** Public ContentPageController
**Effort:** Small
**Files:**
- `app/Http/Controllers/Api/General/ContentPageController.php`

**Description:** The public `index()` and `show()` methods have commented-out `Cache::remember` calls. Implement proper caching with cache invalidation on content page/section mutations.

**Acceptance Criteria:**
- [ ] `GET /api/v1/general/content-pages` is cached (24h TTL or configurable)
- [ ] `GET /api/v1/general/content-pages/{slug}` is cached per slug
- [ ] Cache invalidated when any content page or section is created/updated/deleted
- [ ] Cache keys scoped by channel (if multi-channel)
- [ ] Tests for cache hit/miss behavior

---

## Task 2: Remove Dead Code in SectionController@store

**Priority:** Low
**Component:** SectionController
**Effort:** Trivial
**Files:**
- `packages/marvel/src/Http/Controllers/SectionController.php`

**Description:** Lines 42-48 contain commented-out code for auto-creating section types and upserting settings during section creation. Remove or uncomment if needed.

**Acceptance Criteria:**
- [ ] Dead code removed
- [ ] No change in behavior

---

## Task 3: Add Pagination to Sections Index

**Priority:** Low
**Component:** SectionController
**Effort:** Small
**Files:**
- `packages/marvel/src/Http/Controllers/SectionController.php`

**Description:** `GET /api/v1/sections` returns ALL sections without pagination. With many sections, this could cause performance issues. Add configurable pagination.

**Acceptance Criteria:**
- [ ] Default limit (e.g., 50) with ?limit and ?page query params
- [ ] Backward compatible (or update frontend)
- [ ] Sortable scope `ordered()` preserved

---

## Task 4: Remove Redundant `byType` Method

**Priority:** Low
**Component:** SectionTypeController
**Effort:** Trivial
**Files:**
- `packages/marvel/src/Http/Controllers/SectionTypeController.php`

**Description:** `SectionTypeController::settings($type)` and `byType($type)` return identical data (grouped settings). The `byType` method is not exposed in routes. Remove it.

**Acceptance Criteria:**
- [ ] `byType` method removed
- [ ] No routes affected
- [ ] No tests broken

---

## Task 5: Fix Attach Sections Empty Message

**Priority:** Low
**Component:** ContentPageController
**Effort:** Trivial
**Files:**
- `packages/marvel/src/Http/Controllers/ContentPageController.php`

**Description:** When `attachSections` receives an empty array, it returns `DELETE_DATA_SUCCESSFULLY` (translation: "deleted successfully") but actually it detaches sections (sets content_page_id to null), not deletes them. Use a more accurate message key.

**Acceptance Criteria:**
- [ ] Message says "Sections detached successfully" or similar
- [ ] Translation key added to `resources/lang/en/message.php`
