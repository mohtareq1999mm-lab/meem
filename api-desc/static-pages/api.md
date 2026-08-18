# API Reference — Static Pages Module

Base URL: `/api/v1`
Public endpoints: no auth required.
Admin endpoints: require `auth:sanctum` + `email.verified` + explicit permission (spatie).

> Static pages are **fixed, seeded pages** (About Us, Terms & Conditions, Privacy Policy). The page
> set and their slugs are immutable at runtime: there are **no create/delete page endpoints**. Only
> their translatable `title` and `is_active` flag can be updated. All content lives in **static
> sections** which support full CRUD + reorder.

---

## Authentication & Permissions

| Endpoint | Required permission |
|----------|---------------------|
| `GET /static-pages` (admin) | `view-static-pages` |
| `GET /static-pages/{slug}` (admin) | `view-static-pages` |
| `PUT /static-pages/{slug}` | `update-static-pages` |
| `POST /static-pages/{slug}/sections` | `create-static-sections` |
| `PUT /static-pages/{slug}/sections/{id}` | `update-static-sections` |
| `DELETE /static-pages/{slug}/sections/{id}` | `delete-static-sections` |
| `POST /static-pages/{slug}/sections/reorder` | `update-static-sections` |

Public `general/*` endpoints require no auth.

---

## Standard Response Envelope

All endpoints use the `ApiResponse` envelope:

```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": {}
}
```

Error responses drop `data`:

```json
{
  "status": 404,
  "message": "Not Found",
  "success": false
}
```

Validation failures return **422** with a flat error map (no `errors` wrapper):

```json
{
  "title": ["The title field is required."],
  "title.en": ["The title (English) field is required."]
}
```

---

## Resource Shapes

### StaticPageResource

| Field | Type | Notes |
|-------|------|-------|
| id | int | |
| slug | string | Immutable. One of the seeded slugs. |
| title | string | Localized per `lang` header (`en`/`ar`, default `en`). |
| is_active | bool | |
| sections | array | StaticSectionResource[] — only present when relation is loaded. Ordered by `order`. |

### StaticSectionResource

| Field | Type | Notes |
|-------|------|-------|
| id | int | |
| static_page_id | int | Owning page. |
| title | string | Localized per `lang` header. |
| content | object | **Full locale map** `{ "en": {...}, "ar": {...} }`. Nested structure is free-form. |
| order | int | Sortable position within the page. |

---

## Public Endpoints

### GET /api/v1/general/static-pages

List all **active** static pages with their sections.

**Headers:**

| Header | Value | Description |
|--------|-------|-------------|
| lang | en, ar | Language code (default: en) |

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
          "content": { "en": { "heading": "Welcome" }, "ar": { "heading": "مرحبا" } },
          "order": 1
        }
      ]
    }
  ]
}
```

**Caching:** response is cached under tag `static_pages`, key `md5(request()->fullUrl())`. The
cached value is the **models**, so the localized `title` still resolves per-request via the
`lang` header. Cache is invalidated by any admin mutation (page update, section
create/update/delete/reorder).

**Quick Test:**
```bash
curl -X GET "http://example.com/api/v1/general/static-pages" \
  -H "Accept: application/json" \
  -H "lang: ar"
```

---

### GET /api/v1/general/static-pages/{slug}

Show one **active** static page by slug with its sections.

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| slug | string | Static page slug (`about-us`, `terms-and-conditions`, `privacy-policy`) |

**Response 200:** StaticPageResource.
**Response 404:** If slug not found OR the page is inactive.

---

## Admin Endpoints

### GET /api/v1/static-pages

List all static pages (regardless of active status) with all sections.

**Permissions:** `view-static-pages`

**Response 200:** Array of StaticPageResource.

---

### GET /api/v1/static-pages/{slug}

Show one static page by slug with all sections.

**Permissions:** `view-static-pages`

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| slug | string | Static page slug (route-model bound via slug, not id) |

**Response 200:** StaticPageResource.
**Response 404:** If slug not found.

---

### PUT /api/v1/static-pages/{slug}

Update a static page title / active status. Page identity (`slug`) can never be changed — any
`slug` in the payload is ignored.

**Permissions:** `update-static-pages`

**Request Body:**
```json
{
  "title": { "en": "Updated About Us", "ar": "من نحن محدث" },
  "is_active": true
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| title | sometimes, array |
| title.* | sometimes, string, max:255 |
| is_active | sometimes, in:0,1 |

**Behavior:** partially supplied locales are merged — a payload with only `title.en` preserves the
existing `title.ar`.

**Response 200:** Updated StaticPageResource.
**Message:** `STATIC_PAGE_UPDATED_SUCCESSFULLY` — "Static page updated successfully".
**Cache:** flushes tag `static_pages`.

---

### POST /api/v1/static-pages/{slug}/sections

Create a new section inside the given page. The section is assigned the next `order` within that
page (sortable, scoped per page).

**Permissions:** `create-static-sections`

**Request Body:**
```json
{
  "title": { "en": "Our Story", "ar": "قصتنا" },
  "content": {
    "en": { "heading": "Welcome", "body": "Hello" },
    "ar": { "heading": "مرحبا", "body": "أهلا" }
  }
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| title | required, array |
| title.en | required, string, max:255 |
| title.* | nullable, string, max:255 |
| content | required, array |
| content.en | array |
| content.ar | array |

**Custom validation:** the top level of `content` must be an **associative object keyed by locale**.
A top-level JSON list is rejected with message `STATIC_SECTION_CONTENT_INVALID` ("The section
content must be an object keyed by locale").

**Response 200:** Created StaticSectionResource.
**Message:** `STATIC_SECTION_CREATED_SUCCESSFULLY`.
**Cache:** flushes tag `static_pages`.

---

### PUT /api/v1/static-pages/{slug}/sections/{id}

Update a section belonging to the given page.

**Permissions:** `update-static-sections`

**Request Body:**
```json
{
  "title": { "en": "Updated Story" },
  "content": { "en": { "heading": "New Heading" } }
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| title | sometimes, array |
| title.en | sometimes, string, max:255 |
| title.* | sometimes, nullable, string, max:255 |
| content | sometimes, array |
| content.en | array |
| content.ar | array |

**Custom validation:** same top-level list rejection as create.

**Behavior:**
- Partial locale maps are merged — existing locales not sent are preserved.
- If the section id belongs to a **different** page, `404` is returned (existence is never leaked
  through another page's route).

**Response 200:** Updated StaticSectionResource.
**Response 404:** Section not found or owned by another page.
**Message:** `STATIC_SECTION_UPDATED_SUCCESSFULLY`.
**Cache:** flushes tag `static_pages`.

---

### DELETE /api/v1/static-pages/{slug}/sections/{id}

Delete a section belonging to the given page. Deleting a middle section does not renumber the
remaining sections; the next created section gets `max(order) + 1`.

**Permissions:** `delete-static-sections`

**Response 200:** `{ "status": 200, "message": "...", "success": true }`.
**Response 404:** Section not found or owned by another page.
**Message:** `STATIC_SECTION_DELETED_SUCCESSFULLY`.
**Cache:** flushes tag `static_pages`.

---

### POST /api/v1/static-pages/{slug}/sections/reorder

Reorder the sections of the given page by supplying their IDs in the new order. The reorder is
strictly scoped to the page — a foreign section id results in `404`.

**Permissions:** `update-static-sections`

> Note: this route must be declared **before** `sections/{id}` so `reorder` is never captured by
> the parameterized route.

**Request Body:**
```json
{
  "sections": [3, 1, 2]
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| sections | required, array |
| sections.* | required, integer, distinct, exists:static_sections,id |

**Behavior:** every supplied id must belong to the given page, otherwise `404`. The DB update is
additionally scoped by `static_page_id` as a second safety layer (the sortable `setNewOrder` runs
raw, so the controller explicitly flushes cache).

**Response 200:** `{ "status": 200, "message": "...", "success": true }`.
**Response 404:** Any id does not belong to the page.
**Message:** `STATIC_SECTIONS_REORDERED_SUCCESSFULLY`.
**Cache:** flushes tag `static_pages`.

---

## Fixed-Page Invariants

| Rule | Enforcement |
|------|-------------|
| Page set is fixed to `about-us`, `terms-and-conditions`, `privacy-policy` | `StaticPageSeeder` `firstOrCreate`, idempotent, never overwrites titles/`is_active`, never deletes sections |
| No page creation | No `POST /static-pages` route → `405` |
| No page deletion | No `DELETE /static-pages/{slug}` route → `405` |
| Slug immutable | `PUT` request only forwards `title` + `is_active` |
| Sections belong to one page | FK `static_page_id` NOT NULL + cascade delete; cross-page access → `404` |
