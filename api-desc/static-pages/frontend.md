# Static Pages Module — Frontend Integration Guide

---

## Overview

Static pages are **fixed, seeded pages** (About Us, Terms & Conditions, Privacy Policy). The page
set and slugs never change — the frontend only renders pages and lets admins edit their content
sections. There is no create/delete page UI.

Every page has a translatable `title`, an `is_active` flag, and an ordered list of free-form
`content` sections. The `content` shape is not fixed by the backend, so the frontend must render
it generically and let admins edit it as a per-locale object tree.

---

## Locale Handling (Critical)

All titles and content are translated via the `lang` header (`en` / `ar`, default `en`).

- Public requests must send the header: `lang: en` or `lang: ar`
- The response `title` is **already localized** (a plain string) for the requested lang
- The response `content` is a **full locale map** `{ "en": {...}, "ar": {...} }` — pick the active
  locale key, fall back to `en` when missing
- When the UI locale changes, re-fetch (the server-side cache stores models, so a fresh request
  with the new `lang` header returns the correct localization)

```
fetch('/api/v1/general/static-pages', { headers: { lang: 'ar', Accept: 'application/json' } })
```

---

## Public Endpoints (No Auth)

### 1. GET /api/v1/general/static-pages — List Active Pages

**Response 200:**
```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": [
    {
      "id": 1,
      "slug": "about-us",
      "title": "About Us",
      "is_active": true,
      "sections": [
        {
          "id": 1,
          "static_page_id": 1,
          "title": "Our Story",
          "content": { "en": { "heading": "Welcome", "body": "Hello" }, "ar": { "heading": "مرحبا", "body": "أهلا" } },
          "order": 1
        }
      ]
    }
  ]
}
```

### 2. GET /api/v1/general/static-pages/{slug} — Show One Active Page

**Response 200:** Same StaticPageResource structure.
**Response 404:** Slug not found OR the page is inactive.

> Inactive pages are invisible to the public — treat 404 as "page not available".

---

## Admin Endpoints (Auth + Permission)

All admin responses use the same envelope and resources. Permissions are enforced server-side;
the frontend should hide/disable actions the current user cannot perform.

| Endpoint | Permission | Use in UI |
|----------|------------|-----------|
| `GET /api/v1/static-pages` | view-static-pages | Page list |
| `GET /api/v1/static-pages/{slug}` | view-static-pages | Edit form (load) |
| `PUT /api/v1/static-pages/{slug}` | update-static-pages | Save title/active |
| `POST /api/v1/static-pages/{slug}/sections` | create-static-sections | Add section |
| `PUT /api/v1/static-pages/{slug}/sections/{id}` | update-static-sections | Edit section |
| `DELETE /api/v1/static-pages/{slug}/sections/{id}` | delete-static-sections | Delete section |
| `POST /api/v1/static-pages/{slug}/sections/reorder` | update-static-sections | Drag-and-drop reorder |

---

## Frontend Flow — Public Page Rendering

```
Route: /pages/{slug}  (e.g. /pages/about-us)
    │
    ▼
1. GET /api/v1/general/static-pages/{slug}  (headers: lang + Accept)
    │
    ▼
2. 200 → page object (title localized, sections ordered by `order`)
   │
   ▼
3. Render <StaticPageRenderer>
   ├─ Page <h1>: title
   └─ For each section (sorted by `order`):
        <StaticSection>
          ├─ <h2>: section.title  (render only if non-empty)
          └─ <ContentBlocks content={section.content} lang={activeLocale} />
                └─ pick content[activeLocale] (fallback content.en)
                   then map the object generically:
                   { heading } → h3
                   { body }    → paragraph
                   { image }   → <img src>
                   { list }    → <ul>
                   { blocks }  → nested recursive blocks
                   other keys  → key: value definition list
   │
   ▼
4. 404 → Not Found page (inactive or unknown)
```

### Free-form Content Rendering

Since `content` has no fixed schema, render a small **block renderer** that walks the locale map:

```js
function renderBlocks(node) {
  if (Array.isArray(node))        return node.map(renderBlocks);
  if (typeof node === 'object')   return Object.entries(node).map(([k, v]) =>
      k === 'heading' ? <h3>{renderText(v)}</h3>
    : k === 'body'    ? <p>{renderText(v)}</p>
    : k === 'image'   ? <img src={v} />
    : k === 'list'    ? <ul>{v.map(renderBlocks)}</ul>
    : <dl><dt>{k}</dt><dd>{renderBlocks(v)}</dd></dl>);
  return <span>{String(node)}</span>;
}
```

This guarantees any content an admin writes still renders, and unknown keys degrade gracefully
instead of crashing.

---

## Frontend Flow — Admin Management

### Screen A: Static Pages List

1. `GET /api/v1/static-pages` → table of the 3 fixed pages
2. Columns: slug (read-only), localized title, active badge, actions (Edit)
3. No create/delete buttons — slugs are immutable (endpoints return 405)

### Screen B: Page Editor

1. `GET /api/v1/static-pages/{slug}` → load page + sections
2. Title inputs per locale (EN + AR); active toggle
3. Save → `PUT /api/v1/static-pages/{slug}` with `{ "title": { "en": "...", "ar": "..." }, "is_active": true }`
   - Partial maps allowed: `{ "title": { "en": "..." } }` keeps the existing `ar`
4. Below: the **Sections Manager** for this page (Screen C)

### Screen C: Sections Manager (per page)

1. List sections sorted by `order`
2. **Add/Edit section** — form with:
   - Title: EN/AR inputs (partial maps OK)
   - Content: per-locale object editor (see jira-frontend Task 4) — JSON tree per locale
3. Create → `POST /api/v1/static-pages/{slug}/sections`
4. Edit → `PUT /api/v1/static-pages/{slug}/sections/{id}`
5. Delete → `DELETE /api/v1/static-pages/{slug}/sections/{id}` (confirmation modal)
6. **Reorder** — drag-and-drop list → send full ordered id array:
   ```json
   { "sections": [3, 1, 2] }
   ```
   to `POST /api/v1/static-pages/{slug}/sections/reorder`

---

## Data-Shape Contract Checklist

When wiring API calls, verify every payload matches these exact shapes:

| Call | Payload |
|------|---------|
| Update page | `{ "title": { "en": "…", "ar": "…" }, "is_active": true }` |
| Create section | `{ "title": { "en": "…", "ar": "…" }, "content": { "en": {…}, "ar": {…} } }` |
| Update section | same as create (partial maps allowed) |
| Reorder | `{ "sections": [3, 1, 2] }` |

**Validation notes (422):**
- `title` is required on create; `title.en` is required
- `content` is required and must be an **object keyed by locale** — sending a JSON array at the
  top level is rejected with `"The section content must be an object keyed by locale"`
- 422 returns a **flat error map** (no `errors` wrapper), e.g.:
  ```json
  { "title": ["The title field is required."], "title.en": ["The title (English) field is required."] }
  ```

---

## State Handling

| State | Behavior |
|-------|----------|
| **Loading** | Skeleton page + section placeholders |
| **Empty** | Page with no sections → "No content" placeholder |
| **Error (page)** | Toast with retry; 404 → not-found page |
| **Section loading** | Skeleton per section |
| **Section error** | Show section error, keep rendering other sections |
| **Locale change** | Re-fetch page data with new `lang` header |
| **Save (admin)** | Button spinner; disable until request resolves |
| **422** | Inline field errors + toast |

---

## Delivery Checklist (Frontend Is Next)

- [ ] API client helper that always sends `lang` + `Accept: application/json`
- [ ] Public `/pages/{slug}` route + renderer with the generic block renderer
- [ ] Admin: pages list, page editor (localized title + active), sections manager, reorder, delete
- [ ] Permission-aware UI (hide actions the role cannot perform)
- [ ] Loading / empty / error states on every screen
- [ ] Locale fallback (`content[lang]` → `content.en` → render raw values)
