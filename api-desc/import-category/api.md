# API Reference — Category Import / Export

---

## Admin Endpoints

---

### POST /api/v1/categories/import

Upload an Excel file and queue an asynchronous category import.

The HTTP request **only queues the import**. The actual Excel processing happens asynchronously via `ImportCategoriesJob` on the `meem-high` queue.

**Authentication**: `auth:sanctum`, permission: `import-category` (or `super_admin`)

**Request** (multipart/form-data):

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| file | file | required | Excel file (xlsx, xls, ods) |

**Validation Rules**:

| Field | Rules |
|-------|-------|
| file | required, file, mimes:xlsx,xls,ods, max:20480 (KB = 20 MB) |

**Accepted formats**: `.xlsx`, `.xls`, `.ods`

**Response 202**:
```json
{
  "status": 202,
  "message": "Category import started successfully",
  "success": true,
  "data": {
    "import_id": 42,
    "status": "pending"
  }
}
```

**Response 422** (validation):
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "file": ["The file field is required."]
  }
}
```

**Response 401** (unauthenticated):
```json
{
  "status": 401,
  "message": "Unauthenticated.",
  "success": false
}
```

**Response 403** (forbidden — lacks `import-category`):
```json
{
  "status": 403,
  "message": "This action is unauthorized.",
  "success": false
}
```

**Quick Test**:
```bash
curl -X POST "http://example.com/api/v1/categories/import" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -F "file=@categories.xlsx"
```

**Business Rules**:
- Uploaded file is stored on the `public` disk under `imports/`
- An `imports` row is created with `type = category`, `status = pending`
- `ImportCategoriesJob` is dispatched on the `meem-high` queue (tries 3, timeout 1500s)
- Track progress with `GET /categories/import/{id}` using the returned `import_id`

---

### GET /api/v1/categories/import/sample

Download the official Category Excel import template.

**Authentication**: `auth:sanctum`, permission: `import-category` (or `super_admin`)

**Response 200**: Binary `.xlsx` file (`category-import-sample.xlsx`)

**Columns** (header row 1, data from row 2):
```
name_en | name_ar | details_en | details_ar | parent_name_en | status | is_featured | image_desktop_url | image_mobile_url
```

**Quick Test**:
```bash
curl -X GET "http://example.com/api/v1/categories/import/sample" \
  -H "Authorization: Bearer {token}" \
  -o category-import-sample.xlsx
```

**Business Rules**:
- Returns the official template located at `packages/marvel/resources/categories/category-import-sample.xlsx`
- The template must NOT be modified structurally (add/remove columns) — the importer expects the exact 9 columns

---

### GET /api/v1/categories/import/{id}

Fetch the status and progress of a queued import.

**Authentication**: `auth:sanctum`, permission: `import-category` (or `super_admin`)

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| id | int | Import ID (from `POST /categories/import`) |

**Response 200**:
```json
{
  "status": 200,
  "message": "Category import status fetched successfully",
  "success": true,
  "data": {
    "id": 42,
    "status": "processing",
    "total_rows": 120,
    "processed_rows": 40,
    "successful_rows": 35,
    "failed_rows": 5,
    "progress": 33.33,
    "errors": [],
    "error_count": 0,
    "created_at": "2026-08-18T10:15:00+00:00",
    "completed_at": null
  }
}
```

**Response 200** (terminal, with errors):
```json
{
  "status": 200,
  "message": "Category import status fetched successfully",
  "success": true,
  "data": {
    "id": 42,
    "status": "completed_with_errors",
    "total_rows": 120,
    "processed_rows": 120,
    "successful_rows": 118,
    "failed_rows": 2,
    "progress": 100.0,
    "errors": [
      {
        "sheet": "categories",
        "row": 5,
        "name_en": "Smartwatches",
        "name_ar": "ساعات ذكية",
        "parent_name_en": "Electronics",
        "error_message": "The parent category 'Electronics' was not found."
      }
    ],
    "error_count": 2,
    "created_at": "2026-08-18T10:15:00+00:00",
    "completed_at": "2026-08-18T10:16:05+00:00"
  }
}
```

**Response 404**:
```json
{ "status": 404, "message": "Not found", "success": false }
```

**Quick Test**:
```bash
curl -X GET "http://example.com/api/v1/categories/import/42" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Supported Statuses**:

| Status | Meaning |
|--------|---------|
| `pending` | Queued, not yet picked up by the worker |
| `processing` | Job is actively processing the Excel rows |
| `completed` | All rows processed successfully |
| `completed_with_errors` | Finished, some rows failed (details in `errors`) |
| `failed` | The whole import failed (e.g., system error) |
| `cancelled` | Import was cancelled by the user |
| `cancelling` | Transient — a cancel request has been received and is being honored |

**Business Rules**:
- `successful_rows` is the API field name. The database stores the same value in `success_rows`; the API maps it to `successful_rows`.
- `error_count` = number of items in `errors`
- `progress` is `100.0` when `completed` / `completed_with_errors`; otherwise read from the progress signal file
- `completed_at` is only present (non-null) for terminal states (`completed`, `completed_with_errors`, `failed`, `cancelled`) and equals the `updated_at` timestamp
- `status` reports `cancelling` while a cancel signal exists, even if the DB status is still `processing`
- Response is never cached (Cache-Control: no-cache)

---

### POST /api/v1/categories/import/{id}/cancel

Cancel a pending or processing import.

**Authentication**: `auth:sanctum`, permission: `import-category` (or `super_admin`)

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| id | int | Import ID |

**Response 200**:
```json
{
  "status": 200,
  "message": "Import cancelled successfully",
  "success": true,
  "data": {
    "import_id": 42,
    "status": "cancelled"
  }
}
```

**Response 409** (already terminal — cannot cancel):
```json
{
  "status": 409,
  "message": "Import cannot be cancelled in its current state",
  "success": false
}
```

**Response 404**:
```json
{ "status": 404, "message": "Not found", "success": false }
```

**Quick Test**:
```bash
curl -X POST "http://example.com/api/v1/categories/import/42/cancel" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Business Rules**:
- Cancellation is allowed while the import is `pending` or `processing`
- Terminal states (`completed`, `completed_with_errors`, `failed`, `cancelled`) return **409**
- A cancellation signal file is written; the running job detects it, rolls back created categories (soft delete), and marks the import `cancelled`
- Categories created before cancellation are soft-deleted (rolled back)

---

### GET /api/v1/categories/import/{id}/download-errors

Download the failed rows of an import as an Excel file.

**Authentication**: `auth:sanctum`, permission: `import-category` (or `super_admin`)

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| id | int | Import ID |

**Response 200**: Binary `.xlsx` file (`failed_category_import_rows_{id}.xlsx`)

**File columns**:

| Column | Description |
|--------|-------------|
| Sheet | Sheet name (`categories`) |
| Row | Excel row number (data rows start at 2) |
| Name (EN) | English name from the failed row |
| Name (AR) | Arabic name from the failed row |
| Parent Name (EN) | Parent English name from the failed row |
| Error Message | The error that caused the row to fail |

**Response 404** (import has no errors):
```json
{
  "status": 404,
  "message": "No errors found",
  "success": false
}
```

**Response 404** (import not found):
```json
{ "status": 404, "message": "Not found", "success": false }
```

**Quick Test**:
```bash
curl -X GET "http://example.com/api/v1/categories/import/42/download-errors" \
  -H "Authorization: Bearer {token}" \
  -o failed_category_import_rows_42.xlsx
```

**Business Rules**:
- Available whenever the import has recorded errors (even while the import is still `processing`)
- Returns 404 `No errors found` when `errors` is empty
- The file is generated on the fly and deleted after download

---

### GET /api/v1/categories/export

Queue an asynchronous Excel export of all categories.

The HTTP request **only queues the export**. The actual file generation happens asynchronously via `ExportCategoriesJob` on the `meem-high` queue.

**Authentication**: `auth:sanctum`, permission: `export-category` (or `super_admin`)

**Query Parameters**: None. The current implementation exports **all** categories (ordered by `level`, then `id`). No filter parameters are supported.

**Response 202**:
```json
{
  "status": 202,
  "message": "Category export started successfully",
  "success": true,
  "data": {
    "export_id": 58,
    "status": "pending"
  }
}
```

**Response 401** / **403**: Same envelopes as the import endpoint.

**Quick Test**:
```bash
curl -X GET "http://example.com/api/v1/categories/export" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Business Rules**:
- An `imports` row is created with `type = category-export`, `status = pending`
- `ExportCategoriesJob` is dispatched on the `meem-high` queue (tries 2, timeout 600s)
- The exported file is written to the `public` disk
- Track progress with `GET /categories/export/{id}` using the returned `export_id`

---

### GET /api/v1/categories/export/{id}

Fetch the status of a queued export.

**Authentication**: `auth:sanctum`, permission: `export-category` (or `super_admin`)

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| id | int | Export ID (from `GET /categories/export`) |

**Response 200**:
```json
{
  "status": 200,
  "message": "Category export status fetched successfully",
  "success": true,
  "data": {
    "id": 58,
    "status": "completed",
    "total_rows": 120,
    "processed_rows": 120,
    "successful_rows": 120,
    "failed_rows": 0,
    "errors": [],
    "created_at": "2026-08-18T10:20:00+00:00",
    "completed_at": "2026-08-18T10:20:04+00:00"
  }
}
```

**Response 404**:
```json
{ "status": 404, "message": "Not found", "success": false }
```

**Quick Test**:
```bash
curl -X GET "http://example.com/api/v1/categories/export/58" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Supported Statuses**:

| Status | Meaning |
|--------|---------|
| `pending` | Queued, not yet picked up by the worker |
| `processing` | Job is generating the Excel file |
| `completed` | File is ready for download |
| `failed` | Export failed |

**Business Rules**:
- `successful_rows` maps from the database `success_rows`
- `completed_at` is present for terminal states (`completed`, `completed_with_errors`, `failed`, `cancelled`)
- Once `status = completed`, use `GET /categories/export/{id}/download`
- Response is never cached (Cache-Control: no-cache)

---

### GET /api/v1/categories/export/{id}/download

Download the generated Excel export.

**Authentication**: `auth:sanctum`, permission: `export-category` (or `super_admin`)

**Path Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| id | int | Export ID |

**Response 200**: Binary `.xlsx` file (`categories-export-{timestamp}.xlsx`)

**Response 409** (export not ready):
```json
{
  "status": 409,
  "message": "Export file is not ready yet",
  "success": false
}
```

**Response 404**:
```json
{ "status": 404, "message": "Not found", "success": false }
```

**Quick Test**:
```bash
curl -X GET "http://example.com/api/v1/categories/export/58/download" \
  -H "Authorization: Bearer {token}" \
  -o categories-export.xlsx
```

**Business Rules**:
- Only available when the export `status = completed` **and** the generated file exists on the `public` disk
- Returns **409** while the export is still `pending`/`processing`, when it `failed`, or when the file is missing
- The exported Excel uses the exact 9 import columns and **can be edited and re-imported**

---

## Excel Format Reference

The exact columns used by both Import (`CategoriesImport`) and Export (`CategoriesExport`):

| Column | Required (Import) | Description |
|--------|-------------------|-------------|
| name_en | Yes | English name — **import identity** |
| name_ar | Yes | Arabic name |
| details_en | No | English details |
| details_ar | No | Arabic details |
| parent_name_en | No | Parent Category **English name** (empty = root) |
| status | No | `1`/`0` (also accepts `true/false/yes/no/on/off`; empty → 1) |
| is_featured | No | `1`/`0` (also accepts `true/false/yes/no/on/off`; empty → 0) |
| image_desktop_url | No | Public URL of the desktop image |
| image_mobile_url | No | Public URL of the mobile image |

> **The Excel MUST NOT contain** `id`, `slug`, `parent_id`, or `parent_slug` columns. These values are never read from the file; `slug` is always generated by the backend.

## Import Identity Behavior

1. Normalize `name_en` (trim + collapse internal whitespace).
2. Search for an existing Category using the normalized English name.
3. **If found** → update that Category (name, details, status, is_featured).
4. **If not found** → generate a deterministic slug using `Str::slug()`.
5. **If the generated slug is already assigned to another Category** → the row fails with a "slug conflict" error.
6. `globalSlugify()` is **never** used for import slug generation.
7. A random suffix is **never** appended to the slug.
8. An existing Category is **never** silently overwritten by a new one.

## Parent Handling

| `parent_name_en` | Result |
|------------------|--------|
| empty | Root category (`parent_id = null`, level 1) |
| a Category's English name | Parent resolved → `parent_id` set, level = parent.level + 1 |

- **Missing parent** — no Category matches the name → row error
- **Ambiguous parent** — multiple Categories share the English name → row error
- **Self-parent** — a Category cannot be its own parent → row error
- **Circular hierarchy** — assigning a descendant as a parent is detected and rejected → row error
- **Multi-level hierarchy** — arbitrary depth is supported (e.g., Electronics → Phones → Smartphones)
- **Row-order independence** — parents may appear on any row (before, after, or not at all); parent resolution runs after all rows are upserted

## Image Import

| Property | Value |
|----------|-------|
| `image_desktop_url` / `image_mobile_url` | Optional, URL-based |
| Protocols | `http`, `https` |
| Supported formats | `jpeg`, `jpg`, `png`, `gif` |
| **SVG** | **Not supported** for Excel image import |
| Max size | 5 MB |
| Redirect limit | 5 |
| Timeout | 30 s |

Image URLs are protected against **SSRF**: private, loopback, link-local, reserved, and cloud metadata addresses are blocked, every redirect is re-validated, and the downloaded content must be a genuine JPEG/PNG/GIF (verified via MIME detection and image dimensions). Unsafe or unsupported URLs fail the row with an "unsafe image URL" / "unsupported image type" error.