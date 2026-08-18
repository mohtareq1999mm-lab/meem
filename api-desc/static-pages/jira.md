# Static Pages Module — Backend Jira Tasks

---

## Task 1: Add `is_active` Toggle for Static Sections

**Priority:** Medium
**Component:** StaticSection model + StaticPageController (admin) + public controller
**Effort:** Medium
**Files:**
- `database/migrations/2026_08_18_000002_create_static_sections_table.php`
- `packages/marvel/src/Database/Models/StaticSection.php`
- `packages/marvel/src/Http/Controllers/StaticPageController.php`
- `app/Http/Controllers/Api/General/StaticPageController.php`
- `packages/marvel/src/Http/Requests/StoreStaticSectionRequest.php`, `UpdateStaticSectionRequest.php`

**Description:** Static sections currently have no visibility flag — every section created is
always returned by the public API. Add an `is_active` boolean (default true) with a
create/update toggle, and filter `where('is_active', true)` on the public endpoints only.

**Acceptance Criteria:**
- [ ] `is_active` column added to `static_sections`
- [ ] Create/update requests accept `is_active` (`nullable|in:0,1`)
- [ ] Public index/show include active sections only
- [ ] Admin index/show include all sections
- [ ] Cache flush on toggle (observer + controller already flush `static_pages` tag)
- [ ] Tests: public hides inactive section, admin sees it, toggle persists

---

## Task 2: Cache Key Must Account for Rendered Locale (future-proofing)

**Priority:** Low
**Component:** Public StaticPageController
**Effort:** Small
**Files:**
- `app/Http/Controllers/Api/General/StaticPageController.php`

**Description:** The cache key is `md5(request()->fullUrl())`, which does not include the `lang`
header. This is safe **today** because the cached value is the raw models and locale is resolved
at render time. If the cache is ever switched to store rendered resources (or content becomes
locale-dependent at the query level), the key must include the locale.

**Acceptance Criteria:**
- [ ] Document the current invariant in the controller comment
- [ ] If/when rendering moves into the cache, include `app()->getLocale()` in the key
- [ ] No behavior change

---

## Task 3: Paginate Admin Static Pages Index

**Priority:** Low
**Component:** StaticPageController (admin)
**Effort:** Small
**Files:**
- `packages/marvel/src/Http/Controllers/StaticPageController.php`
- `app/Services/General/StaticPageService.php`

**Description:** `GET /api/v1/static-pages` returns all pages without pagination. With the page set
fixed to 3 this is fine today, but the admin index should support `page`/`per_page` query params
to stay consistent with other admin lists.

**Acceptance Criteria:**
- [ ] `?page` and `?per_page` query params supported
- [ ] Backward compatible with fixed set (no frontend change required)
- [ ] Existing tests unaffected

---

## Task 4: Fix Stale `FrontendResourceTest` Assertion

**Priority:** Low
**Component:** Tests — FrontendResource enum
**Effort:** Trivial
**Files:**
- `tests/Unit/FrontendResourceTest.php`

**Description:** `it_has_exactly_fifteen_resources` asserts the enum has exactly 15 cases, but it
now has 25 (previous modules + the new `STATIC_PAGES = 'static_pages'` case). This test was
already failing before Static Pages. Update it to assert the enum contains the expected cases
(inclusion) instead of an exact count.

**Acceptance Criteria:**
- [ ] Test asserts `STATIC_PAGES` is present with value `'static_pages'`
- [ ] Exact-count assertion removed or made additive
- [ ] Test green

---

## Task 5: Validate `content` Shape Consistency Across Locales

**Priority:** Low
**Component:** UpdateStaticSectionRequest
**Effort:** Small
**Files:**
- `packages/marvel/src/Http/Requests/UpdateStaticSectionRequest.php`

**Description:** `content` is free-form by design, but partial locale updates currently let each
locale diverge in shape (e.g., `en` has `{ heading, body }`, `ar` has `{ title }`). Decide whether
this is acceptable (documented) or add optional structural validation for keys that already exist
in another locale.

**Acceptance Criteria:**
- [ ] Decision documented in `api-desc/static-pages/api.md` (free-form is intentional)
- [ ] If enforced, add after-hook validation comparing locale key sets
- [ ] Tests for mixed-shape payloads
