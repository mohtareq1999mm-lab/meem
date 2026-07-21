# Bug Report — Pages Module

---

## BUG-PAGE-001: `attachSections` Returns "Deleted" Message When Detaching

**Severity:** Low

**Component:** `packages/marvel/src/Http/Controllers/ContentPageController.php` (line 73)

**Description:** When `attachSections` receives an empty `sections` array, it calls `$content_page->sections()->update(['content_page_id' => null])` which **detaches** sections (sets the FK to null), but returns `DELETE_DATA_SUCCESSFULLY` as the message. The sections are not deleted — they still exist in the database. The message is misleading.

---

## BUG-PAGE-002: No Caching on Public Endpoints

**Severity:** Medium

**Component:** `app/Http/Controllers/Api/General/ContentPageController.php`

**Description:** The `index()` and `show()` methods have `Cache::remember` calls commented out. Every public page request hits the database for both the page query and the sections query. For high-traffic home pages, this is unnecessary load. Cache should be implemented with invalidation on page/section mutations.

---

## BUG-PAGE-003: Dead Code in SectionController@store

**Severity:** Low

**Component:** `packages/marvel/src/Http/Controllers/SectionController.php` (lines 42-48)

**Description:** There is commented-out code that auto-creates SectionType and upserts settings when creating a section. This code is never executed. It should either be removed or uncommented if the feature is desired.

---

## BUG-PAGE-004: Sections Index Returns All Records Without Pagination

**Severity:** Low

**Component:** `packages/marvel/src/Http/Controllers/SectionController.php` (line 29)

**Description:** `Section::ordered()->get()` returns ALL sections without pagination. A site with hundreds or thousands of sections could experience performance issues. Should use `paginate()` with configurable limit.

---

## BUG-PAGE-005: Redundant `byType` Method in SectionTypeController

**Severity:** Low

**Component:** `packages/marvel/src/Http/Controllers/SectionTypeController.php` (lines 92-100)

**Description:** `byType($type)` and `settings($type)` return identical data. The `byType` method is not referenced in any route definition. It's dead code.

---

## BUG-PAGE-006: Section Settings Cascade No Cache

**Severity:** Low

**Component:** `app/Http/Resources/Pages/SectionResource.php`

**Description:** `getSettings()` queries the `section_types` and `section_type_settings` tables on every serialization. While it caches the result per object instance, a page with many sections will still make 2*N queries for a single page load. This could be optimized with eager loading or a separate cached endpoint.

---

## BUG-PAGE-007: No Soft Deletes

**Severity:** Low

**Component:** All three models (ContentPage, Section, SectionType)

**Description:** Deleting a content page or section is a hard delete. There is no soft delete / trash functionality. Accidental deletions are unrecoverable.
