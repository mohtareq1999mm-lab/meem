# API Reference — Slider

---

## Admin Endpoints

---

### GET /api/v1/sliders

Paginated list of sliders with filtering.

**Authentication**: `auth:sanctum`, permission: `view-slider`

**Query Parameters**:

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| page | int | 1 | Page number |
| limit | int | 10 | Items per page |
| active | bool | - | Filter by status (1 = active, 0 = inactive) |
| order | string | - | Sort column |
| sortedBy | string | desc | Sort direction (asc, desc) |

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
        "title": "Summer Sale",
        "slug": "summer-sale",
        "status": true,
        "order": 1,
        "image": {
          "desktop": "https://example.com/storage/sliders/desktop.jpg",
          "mobile": "https://example.com/storage/sliders/mobile.jpg"
        },
        "products": []
      }
    ],
    "page": 1,
    "current_page": 1,
    "from": 1,
    "to": 10,
    "last_page": 5,
    "path": "http://example.com/api/v1/sliders",
    "per_page": 10,
    "total": 50,
    "next_page_url": "http://example.com/api/v1/sliders?page=2",
    "prev_page_url": "",
    "last_page_url": "http://example.com/api/v1/sliders?page=5",
    "first_page_url": "http://example.com/api/v1/sliders?page=1"
  }
}
```

**Quick Test**:
```bash
curl -X GET "http://example.com/api/v1/sliders?page=1&limit=10" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"

# Filter active only
curl -X GET "http://example.com/api/v1/sliders?active=1" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Business Rules**:
- Paginated with configurable limit (default 10)
- Can filter by `active` to show only active/inactive sliders
- On index, `title` is returned as translated string for current locale

---

### POST /api/v1/sliders

Create a new slider.

**Authentication**: `auth:sanctum`, permission: `create-slider`

**Request Body** (multipart/form-data):

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| title | object | required | Translatable title (e.g., `{"en": "Summer Sale", "ar": "تخفيضات الصيف"}`) |
| title.en | string | required | English title (unique) |
| title.ar | string | required | Arabic title (unique) |
| image_desktop | file | required | Desktop image (jpeg,png,jpg,gif, max 2MB) |
| image_mobile | file | required | Mobile image (jpeg,png,jpg,gif, max 2MB) |
| status | int | sometimes | 0 or 1 |
| products | array | sometimes | Array of product IDs to associate |
| products.* | int | sometimes | Valid product ID |

**Validation Rules**:
| Field | Rules |
|-------|-------|
| title | required, array |
| title.* | required, array |
| title.en | required, string, unique_translation:sliders,title |
| title.ar | required, string, unique_translation:sliders,title |
| image_desktop | required, image, mimes:jpeg,png,jpg,gif, max:2048 |
| image_mobile | required, image, mimes:jpeg,png,jpg,gif, max:2048 |
| status | sometimes, in:1,0 |
| products | sometimes, array |
| products.* | exists:products,id |

**Response 201**:
```json
{
  "status": 201,
  "message": "Slider created successfully",
  "success": true,
  "data": {
    "id": 11,
    "title": {
      "en": "Summer Sale",
      "ar": "تخفيضات الصيف"
    },
    "slug": "summer-sale",
    "status": true,
    "order": 11,
    "image": {
      "desktop": "https://example.com/storage/sliders/desktop.jpg",
      "mobile": "https://example.com/storage/sliders/mobile.jpg"
    },
    "products": []
  }
}
```

**Response 422** (validation):
```json
{
  "title.en": ["The title.en has already been taken."],
  "image_desktop": ["The image_desktop field is required."]
}
```

**Business Rules**:
- Slug is auto-generated from the English title via `Str::slug()` on the `saving` event
- `order` is auto-set via SortableTrait (`sort_when_creating: true`)
- Images are uploaded to `slider-image-desktop` and `slider-image-mobile` collections (create) or `sliders-desktop` and `sliders-mobile` (update)
- Product associations are synced (replaces any existing)

---

### GET /api/v1/sliders/{id}

Get a single slider by ID.

**Authentication**: `auth:sanctum`, permission: `view-slider`

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| id | int | Slider ID |

**Response 200**:
```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": {
    "id": 1,
    "title": {
      "en": "Summer Sale",
      "ar": "تخفيضات الصيف"
    },
    "slug": "summer-sale",
    "status": true,
    "order": 1,
    "image": {
      "desktop": "https://example.com/storage/sliders/desktop.jpg",
      "mobile": "https://example.com/storage/sliders/mobile.jpg"
    },
    "products": [
      {
        "id": 5,
        "name": "Product Name",
        "slug": "product-name",
        "status": "publish",
        "image": { "thumbnail": "..." }
      }
    ]
  }
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

**Business Rules**:
- On `show`, `title` is returned as full translations object (not translated string like index)
- Loads related products when the relation is loaded
- Soft-deleted sliders return 404

---

### PUT /api/v1/sliders/{id}

Update an existing slider.

**Authentication**: `auth:sanctum`, permission: `update-slider`

**Request Body** (multipart/form-data):

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| title | object | sometimes | Translatable title |
| title.en | string | sometimes | English title (unique ignoring self) |
| title.ar | string | sometimes | Arabic title (unique ignoring self) |
| image_desktop | file | sometimes | New desktop image (replaces existing) |
| image_mobile | file | sometimes | New mobile image (replaces existing) |
| status | int | sometimes | 0 or 1 |
| products | array | sometimes | Replaces product associations |

**Response 200**:
```json
{
  "status": 200,
  "message": "Slider updated successfully",
  "success": true,
  "data": {
    "id": 1,
    "title": {
      "en": "Summer Sale (Updated)",
      "ar": "تخفيضات الصيف (مُحدّث)"
    },
    "slug": "summer-sale-updated",
    "status": true,
    "order": 1,
    "image": {
      "desktop": "https://example.com/storage/sliders/desktop-new.jpg",
      "mobile": "https://example.com/storage/sliders/mobile-new.jpg"
    }
  }
}
```

**Business Rules**:
- Slug is re-generated from English title if title changes
- Existing images are replaced (old collection is cleared)
- Products array replaces ALL current associations (sync)

---

### DELETE /api/v1/sliders/{id}

Soft delete a slider.

**Authentication**: `auth:sanctum`, permission: `delete-slider`

**Response 200**:
```json
{
  "status": 200,
  "message": "Slider deleted successfully",
  "success": true
}
```

**Business Rules**:
- Uses soft deletes — record is not removed from database
- Media cleanup happens only on `forceDeleting` (not on soft delete because of the SoftDeletes guard in MediaCleanupObserver)
- Soft-deleted sliders excluded from index and show returns 404

---

### PATCH /api/v1/sliders/change-status

Toggle a slider's active/inactive status.

**Authentication**: `auth:sanctum`, permission: `update-slider`

**Request Body** (JSON):

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| id | int | required | Slider ID |

**Response 200**:
```json
{
  "status": 200,
  "message": "Slider status changed successfully",
  "success": true,
  "data": {
    "id": 1,
    "status": false
  }
}
```

**Business Rules**:
- Toggles the boolean `status` field (true → false, false → true)
- Returns 422 if id is missing or slider not found

---

### PUT /api/v1/sliders/reorder

Reorder sliders by providing a sorted array of IDs.

**Authentication**: `auth:sanctum`, permission: `update-slider`

**Request Body** (JSON):

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| sliders | array | required | Array of slider IDs in desired order |
| sliders.* | int | required | Valid slider ID |

**Response 200**:
```json
{
  "status": 200,
  "message": "Sliders reordered successfully",
  "success": true
}
```

---

## Public Endpoints

---

### GET /api/v1/general/sliders

List active sliders. If `slug` query param is provided, returns a single slider by slug instead.

**Authentication**: None (public)

**Query Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| slug | string | Optional. If provided, returns single slider by slug |
| limit | int | Number of sliders (default: from service) |
| date | date | Filter by date range |

**Response 200** (list):
```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Summer Sale",
      "slug": "summer-sale",
      "status": true,
      "image": {
        "desktop": "https://example.com/storage/sliders/desktop.jpg",
        "mobile": "https://example.com/storage/sliders/mobile.jpg"
      }
    }
  ]
}
```

**Response 200** (by slug):
```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": {
    "id": 1,
    "title": "Summer Sale",
    "slug": "summer-sale",
    "status": true,
    "image": {
      "desktop": "https://example.com/storage/sliders/desktop.jpg",
      "mobile": "https://example.com/storage/sliders/mobile.jpg"
    },
    "products": [
      {
        "id": 5,
        "name": "Product Name",
        "slug": "product-name",
        "image": { "thumbnail": "..." },
        "price": 100,
        "sale_price": 80
      }
    ]
  }
}
```

**Response 404** (by slug not found):
```json
{ "status": 404, "message": "Not found", "success": false }
```

**Quick Test**:
```bash
# List active sliders
curl -X GET "http://example.com/api/v1/general/sliders" \
  -H "Accept: application/json"

# Get slider by slug with products
curl -X GET "http://example.com/api/v1/general/sliders/summer-sale" \
  -H "Accept: application/json"

# Via query param
curl -X GET "http://example.com/api/v1/general/sliders?slug=summer-sale" \
  -H "Accept: application/json"
```

**Business Rules**:
- Only returns sliders with `status = true` (active)
- When fetching by slug, products are enriched with pricing data via channel filter
- Channel filtering applies for multi-channel setups (home vs non-home)
