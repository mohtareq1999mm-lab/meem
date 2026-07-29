# API Reference — Tag

---

## Endpoints

---

### GET /api/v1/tags

Paginated list of tags with language filtering.

**Authentication**: `auth:sanctum`, permission: `view-tags`

**Query Parameters**:

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| page | int | 1 | Page number |
| limit | int | 15 | Items per page |
| language | string | en | Language code (e.g., en, ar) |

**Response 200**:
```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "name": "Organic",
        "slug": "organic",
        "image": null,
        "icon": null
      }
    ],
    "page": 1,
    "current_page": 1,
    "from": 1,
    "to": 15,
    "last_page": 2,
    "path": "http://example.com/api/v1/tags",
    "per_page": 15,
    "total": 20,
    "next_page_url": "http://example.com/api/v1/tags?page=2",
    "prev_page_url": "",
    "last_page_url": "http://example.com/api/v1/tags?page=2",
    "first_page_url": "http://example.com/api/v1/tags?page=1"
  }
}
```

**Quick Test**:
```bash
# List all tags (page 1, 15 per page)
curl -X GET "http://example.com/api/v1/tags?page=1&limit=15" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"

# Filter by language
curl -X GET "http://example.com/api/v1/tags?language=en" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Business Rules**:
- Filters by language (`$request->language ?? DEFAULT_LANGUAGE`)
- Eager-loads `type` relationship
- Returns custom pagination structure (not default Laravel pagination)

---

### POST /api/v1/tags

Create a new tag.

**Authentication**: `auth:sanctum`, permission: `create-tags`

**Request Body** (multipart/form-data):

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | object | required | Translatable name (e.g., `{"en": "Organic", "ar": "عضوي"}`) |
| image | file | sometimes | Tag image (jpeg,png,jpg,gif,svg) |
| icon | file | sometimes | Tag icon (jpeg,png,jpg,gif,svg) |

**Validation Rules**:

| Field | Rules |
|-------|-------|
| name | required, array |
| name.* | required, string, max:150, unique_translation:tags |
| image | nullable, image |
| icon | nullable, string |

**Request Body (JSON)**:
```json
{
  "name": {
    "en": "Organic",
    "ar": "عضوي"
  }
}
```

> **Note:** `image` and `icon` are file fields — they must be sent as `multipart/form-data`, not included in the JSON body.

**Response 201**:
```json
{
  "id": 1,
  "name": "Organic",
  "slug": "organic",
  "image": null,
  "icon": null
}
```

**Response 422** (validation):
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name.en": ["The name en has already been taken."]
  }
}
```

**Quick Test**:
```bash
curl -X POST "http://example.com/api/v1/tags" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name": {"en": "Organic", "ar": "عضوي"}}'
```

**Business Rules**:
- Slug is auto-generated from the name via `makeSlug()` (uses `globalSlugify`)
- If name is a translatable array, the English (`en`) value is used for slug generation
- Image is uploaded to `tags` collection on `tags` disk via `MediaManager::uploadSingleImage()`
- Icon is uploaded to `tags` collection on `tags` disk via `MediaManager::uploadSingleImage()`

---

### GET /api/v1/tags/{id}

Get a single tag by ID.

**Authentication**: `auth:sanctum`, permission: `view-tags`

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| id | int | Tag ID |

**Response 200**:
```json
{
  "id": 1,
  "name": "Organic",
  "slug": "organic",
  "image": null,
  "icon": null
}
```

**Response 404**:
```json
{
  "status": 404,
  "message": "Not found",
  "success": false
}
```

**Quick Test**:
```bash
curl -X GET "http://example.com/api/v1/tags/1" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Business Rules**:
- Accepts numeric ID. If numeric, looks up by `id`.
- If `$params` is non-numeric (string/slug), looks up by `slug` + `language`.
- Eager-loads `type` relationship.
- Returns `TagResource` directly (not wrapped in `apiResponse`).

---

### PUT /api/v1/tags/{id}

Update an existing tag.

**Authentication**: `auth:sanctum`, permission: `update-tags`

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| id | int | Tag ID |

**Request Body** (multipart/form-data):

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | object | sometimes | Translatable name |
| image | file | sometimes | New tag image (replaces existing) |
| icon | string | sometimes | New icon string |

**Validation Rules**:

| Field | Rules |
|-------|-------|
| name | sometimes, array |
| name.* | sometimes, string, max:150, unique_translation:tags ->ignore($id) |
| image | nullable, image |
| icon | nullable, string |

**Request Body (JSON)**:
```json
{
  "name": {
    "en": "Organic Premium",
    "ar": "عضوي ممتاز"
  }
}
```

**Response 200**:
```json
{
  "id": 1,
  "name": "Organic Premium",
  "slug": "organic-premium",
  "image": null,
  "icon": null
}
```

**Response 404**:
```json
{ "status": 404, "message": "Not found", "success": false }
```

**Quick Test**:
```bash
curl -X PUT "http://example.com/api/v1/tags/1" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name": {"en": "Organic Premium"}}'
```

**Business Rules**:
- Slug is auto-regenerated from English name if name is changed
- Uniqueness check ignores the current tag's own name
- Existing images are replaced (old collection is cleared via `updateSingleImage`)
- Returns updated tag from `TagRepository::updateTag()`

---

### DELETE /api/v1/tags/{id}

Delete a tag.

**Authentication**: `auth:sanctum`, permission: `delete-tags`

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| id | int | Tag ID |

**Response 200**:
```json
true
```

**Response 404**:
```json
{ "status": 404, "message": "Not found", "success": false }
```

**Quick Test**:
```bash
curl -X DELETE "http://example.com/api/v1/tags/1" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Business Rules**:
- Uses hard delete (no soft deletes on `tags` table)
- Pivot records in `product_tag` table are cascade-deleted
- Media files are NOT cleaned up on delete
- Returns raw boolean (`true`) on success
