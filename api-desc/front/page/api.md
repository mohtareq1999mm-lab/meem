# API Documentation - Content Page Feature

## Endpoints

---

### 1. List Content Pages (Public)

**GET** `/api/v1/general/pages`

**Purpose:** Retrieve paginated list of active content pages.

#### Authentication

| Aspect | Detail |
|--------|--------|
| Required | No |

#### Success Response (200)

```json
{
    "data": [
        {
            "id": 1,
            "title": "Home",
            "slug": "home",
            "is_active": true,
            "sections": [
                {
                    "id": 1,
                    "type": "sliders",
                    "title": "Hero Sliders",
                    "is_active": true,
                    "endpoint": "/api/v1/general/sliders?limit=5",
                    "order": 0,
                    "setting": { "autoplay": true, "slider_speed": 5000 }
                }
            ]
        }
    ]
}
```

---

### 2. Get Content Page by Slug (Public)

**GET** `/api/v1/general/pages/{slug}`

**Purpose:** Retrieve a single content page with its active sections.

#### Authentication

| Aspect | Detail |
|--------|--------|
| Required | No |

#### Success Response (200)

```json
{
    "data": {
        "id": 1,
        "title": "Home",
        "slug": "home",
        "is_active": true,
        "sections": [
            {
                "id": 1,
                "type": "sliders",
                "title": "Hero Sliders",
                "is_active": true,
                "endpoint": "/api/v1/general/sliders?limit=5",
                "order": 0,
                "setting": { "autoplay": true, "slider_speed": 5000 }
            },
            {
                "id": 2,
                "type": "categories",
                "title": null,
                "is_active": true,
                "endpoint": "/api/v1/general/categories?parentOnly=true",
                "order": 1,
                "setting": { "parentOnly": true }
            }
        ]
    }
}
```

#### Error Responses

| Status | Condition |
|--------|-----------|
| 404 | Slug not found or page inactive |

---

### 3. List CMS Pages

**GET** `/api/v1/cms-pages`

**Purpose:** Retrieve all CMS pages.

#### Authentication

| Aspect | Detail |
|--------|--------|
| Required | No |

---

### 4. Get CMS Page by Slug

**GET** `/api/v1/cms-pages/{slug}`

**Purpose:** Retrieve a CMS page by its URL slug.

#### Success Response (200)

```json
{
    "data": {
        "id": 1,
        "slug": "about-us",
        "title": "About Us",
        "content": [
            { "type": "text", "data": "<p>Our story...</p>", "order": 0 }
        ],
        "meta": { "author": "admin" },
        "created_at": "2026-07-01T00:00:00+00:00",
        "updated_at": "2026-07-15T00:00:00+00:00"
    }
}
```

---

### 5. Get Puck Page by Path

**GET** `/api/v1/puck/page?path=/about`

**Purpose:** Retrieve a page by its path (for Puck page builder rendering).

#### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `path` | `string` | Yes | Page path (e.g., `/about`, `/home`) |

#### Success Response (200)

Returns the same CmsPageResource structure with `data` field containing Puck-format JSON.

---

### 6. Create/Update Puck Page

**POST** `/api/v1/puck/page`

**Purpose:** Create or update a page by its path (upsert).

#### Authentication

| Aspect | Detail |
|--------|--------|
| Required | Yes |
| Role | `super_admin` or `editor` |

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `path` | `string` | Yes | Page path (unique) |
| `title` | `string` | Yes | Page title |
| `slug` | `string` | No | URL slug (nullable) |
| `data` | `object` | No | Puck-format content JSON |
| `content` | `array` | No | Legacy content array |
| `meta` | `object` | No | Page metadata |

#### Success Response (201 created / 200 updated)

---

### 7. Content Pages CRUD (Admin)

**GET** `/api/v1/content-pages` — Paginated list (permission: `view-content-pages`)
**POST** `/api/v1/content-pages` — Create (permission: `create-content-pages`)
**GET** `/api/v1/content-pages/{id}` — Show with sections (permission: `view-content-pages`)
**PUT** `/api/v1/content-pages/{id}` — Update title/is_active (permission: `update-content-pages`)
**DELETE** `/api/v1/content-pages/{id}` — Delete (permission: `delete-content-pages`)

#### Create Request

```json
POST /api/v1/content-pages
{
    "title": { "en": "About Us", "ar": "من نحن" }
}
```

---

### 8. Toggle Page Active Status

**PATCH** `/api/v1/content-pages/{id}/toggle-active`

**Purpose:** Enable or disable a content page.

#### Success Response (200)

```json
{
    "message": "Page status toggled successfully",
    "data": { "id": 1, "is_active": false }
}
```

---

### 9. Attach Sections to Page

**POST** `/api/v1/content-pages/{id}/attach-sections`

**Purpose:** Sync section attachments to a content page.

#### Request

```json
{
    "sections": [1, 3, 5]
}
```

#### Success Response (200)

```json
{
    "message": "Sections attached successfully"
}
```

---

### 10. Component Data Endpoints (Public)

| Endpoint | Purpose | Query Params |
|----------|---------|-------------|
| `GET /api/v1/component-data/categories` | Category block | `limit`, `language`, `topLevelOnly` |
| `GET /api/v1/component-data/collections` | Collection block | `limit`, `language` |
| `GET /api/v1/component-data/flash-sale-products` | Flash sale block | `limit`, `language` |
| `GET /api/v1/component-data/popular-products` | Popular products | `limit`, `language` |
| `GET /api/v1/component-data/best-selling-products` | Best selling products | `limit`, `language` |

---

## Resource Structures

### ContentPageResource (Public)

| Field | Type | Description |
|-------|------|-------------|
| `id` | `integer` | Primary key |
| `title` | `string` | Translated title |
| `slug` | `string` | URL slug |
| `is_active` | `boolean` | Page active status |
| `sections` | `array` | Active sections (when loaded) |

### SectionResource

| Field | Type | Description |
|-------|------|-------------|
| `id` | `integer` | Primary key |
| `type` | `string` | Section type identifier |
| `title` | `string|null` | Translated title (null when `title_visible=false`) |
| `is_active` | `boolean` | Active status |
| `endpoint` | `string` | Auto-generated API endpoint for data fetching |
| `order` | `integer` | Sort order |
| `setting` | `object` | Type-specific configuration |

### CmsPageResource

| Field | Type | Description |
|-------|------|-------------|
| `id` | `integer` | Primary key |
| `slug` | `string` | URL slug |
| `title` | `string` | Page title |
| `content` | `array` | Sorted content blocks |
| `meta` | `object` | Page metadata |
| `created_at` | `string` | Creation timestamp |
| `updated_at` | `string` | Update timestamp |

## Business Rules

1. **Section Endpoint:** Auto-generated as `general/{type}?{back_settings_params}` — frontend uses this to fetch data
2. **Section Settings:** Merged from `section.setting` first, falls back to `SectionType` defaults
3. **Section Title:** Hidden when `title_visible=false` (returns `null`)
4. **Content Order:** Sections are ordered by `order` column (Spatie Sortable)
5. **Active Filtering:** Only `is_active=true` sections returned on public endpoints
6. **Puck Upsert:** Creates or updates by `path` field (unique)
7. **Content Fallback:** `CmsPage` has `getPuckDataAttribute()` that falls back from `data` to `content` field
