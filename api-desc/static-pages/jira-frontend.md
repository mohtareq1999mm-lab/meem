# Static Pages Module — Frontend Jira Tasks

---

## Task 1: Public Static Page Renderer

**Priority:** High
**Component:** Frontend — Public Pages
**Story Points:** 5

**Description:** Build the public renderer that fetches and displays a fixed static page (About
Us, Terms & Conditions, Privacy Policy) with its ordered content sections.

**API Endpoints:**
- `GET /api/v1/general/static-pages`
- `GET /api/v1/general/static-pages/{slug}`

**Response Shape (per page):**
```json
{
  "id": 1,
  "slug": "about-us",
  "title": "About Us",
  "is_active": true,
  "sections": [
    { "id": 1, "static_page_id": 1, "title": "Our Story", "content": { "en": { "heading": "Welcome" }, "ar": { "heading": "مرحبا" } }, "order": 1 }
  ]
}
```

**Acceptance Criteria:**
- [ ] Fetch page by slug from URL route (e.g., `/pages/about-us`)
- [ ] Render sections ordered by the `order` field
- [ ] `title` rendered in the active locale (`lang` header: `en`/`ar`)
- [ ] `content` map rendered per locale; fall back to `en` when the current locale key is missing
- [ ] Inactive pages (404) show the not-found page
- [ ] **Loading state:** Skeleton per section
- [ ] **Empty state:** "No content" placeholder
- [ ] **Error state:** Toast with retry
- [ ] **Section error:** Graceful fallback, continue rendering other sections

---

## Task 2: Admin — Static Pages List & Edit

**Priority:** High
**Component:** Frontend — Admin Panel
**Story Points:** 5

**Description:** Admin screen listing the fixed static pages with an edit form for the localized
title and active status. Page slugs are immutable — no create/delete UI (endpoints return 405).

**API Endpoints:**
- `GET /api/v1/static-pages`
- `GET /api/v1/static-pages/{slug}`
- `PUT /api/v1/static-pages/{slug}`

**Request Body (update):**
```json
{
  "title": { "en": "About Us", "ar": "من نحن" },
  "is_active": true
}
```

**Acceptance Criteria:**
- [ ] Table listing the 3 fixed pages: slug, localized title, active badge
- [ ] Edit form: title (multi-locale EN/AR inputs), active toggle
- [ ] Slug displayed read-only (immutable)
- [ ] Save sends partial locale maps; locale merging is server-side
- [ ] **Loading state:** Table skeleton, form spinner
- [ ] **Error state:** Toast with error message; 422 field errors shown inline
- [ ] **Empty state:** Not applicable (pages always seeded)

---

## Task 3: Admin — Sections Manager per Page

**Priority:** High
**Component:** Frontend — Admin Panel
**Story Points:** 8

**Description:** Manage the ordered content sections of a page: list, create, edit, delete, and
drag-and-drop reorder.

**API Endpoints:**
- `POST /api/v1/static-pages/{slug}/sections`
- `PUT /api/v1/static-pages/{slug}/sections/{id}`
- `DELETE /api/v1/static-pages/{slug}/sections/{id}`
- `POST /api/v1/static-pages/{slug}/sections/reorder`

**Request Body (create/update):**
```json
{
  "title": { "en": "Our Story", "ar": "قصتنا" },
  "content": { "en": { "heading": "Welcome", "body": "Hello" }, "ar": { "heading": "مرحبا", "body": "أهلا" } }
}
```

**Request Body (reorder):**
```json
{ "sections": [3, 1, 2] }
```

**Acceptance Criteria:**
- [ ] Per-page section list ordered by `order`
- [ ] Create form: multi-locale title + content editor (see Task 4)
- [ ] Edit form: all fields editable
- [ ] Drag-and-drop reorder that sends the full ordered id array
- [ ] Delete with confirmation modal
- [ ] **Loading state:** Table skeleton, form spinner, reorder save spinner
- [ ] **Empty state:** "No sections yet" with "Add Section" CTA
- [ ] **Error state:** Toast; 404 shown when the section belongs to another page

---

## Task 4: Admin — Localized Free-form Content Editor

**Priority:** Medium
**Component:** Frontend — Admin Panel
**Story Points:** 8

**Description:** Build the content editor for `content`, which is a free-form object per locale.
Because the shape is unknown at build time, provide a per-locale JSON tree editor or key/value
builder rather than fixed fields.

**Acceptance Criteria:**
- [ ] Tab or split view per locale (`en` / `ar`)
- [ ] Editing a locale key only touches that locale (partial map sent to the API)
- [ ] Structural values supported: strings, numbers, booleans, nested objects, arrays
- [ ] Top-level `content` must remain an object — a JSON list is rejected by the API (422,
      message "The section content must be an object keyed by locale")
- [ ] JSON validation with inline error highlighting
- [ ] **Loading state:** Skeleton while the section loads
- [ ] **Empty state:** Empty-object starter (one empty locale) with "Add locale" button
- [ ] **Error state:** Toast on parse error; field-level error message from 422

---

## Task 5: Locale Handling Across the Module

**Priority:** Medium
**Component:** Frontend — API layer (shared)
**Story Points:** 3

**Description:** Standardize how the frontend sends the locale so titles resolve to the correct
language on public and admin screens.

**Acceptance Criteria:**
- [ ] Public requests send `lang` header from the active UI locale (`en`/`ar`)
- [ ] Re-fetch page data when locale changes (do not trust the cached HTML/state)
- [ ] Admin editing sends both locales in create payloads; update payloads may send one locale
      only (server merges)
- [ ] Fallback: if the active locale key is absent from `content`, fall back to `en`
- [ ] **Error state:** Console warning + English fallback when the header request fails
