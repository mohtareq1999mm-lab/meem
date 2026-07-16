# Attributes & Attribute Values API

## Overview

The Attributes module manages product attribute definitions (e.g., Size, Color) and their values (e.g., S, M, L, XL). Attributes are used to define product variants and filterable product properties. Attribute values can be associated with products and product variants.

---

## Database Schema

### `attributes` Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | PK, AUTO_INCREMENT | Unique identifier |
| `name` | json | NOT NULL | Translatable name (e.g., "Size", "Color") |
| `slug` | varchar(255) | NOT NULL | Auto-generated from English name |
| `created_at` | timestamp | NULLABLE | Creation time |
| `updated_at` | timestamp | NULLABLE | Last update |

### `attribute_values` Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | PK, AUTO_INCREMENT | Unique identifier |
| `value` | json | NOT NULL | Translatable value (e.g., "Small", "Red") |
| `slug` | varchar(255) | NOT NULL | Auto-generated from value |
| `attribute_id` | bigint | FK → attributes.id, CASCADE | Parent attribute reference |
| `created_at` | timestamp | NULLABLE | Creation time |
| `updated_at` | timestamp | NULLABLE | Last update |

### `attribute_product` Pivot Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | PK, AUTO_INCREMENT | Unique identifier |
| `product_variant_id` | bigint | FK → product_variants.id | Variant reference |
| `attribute_value_id` | bigint | FK → attribute_values.id | Attribute value reference |

---

## Response Envelope

All endpoints return:

```json
{
    "status": 200,
    "message": "Translated message string",
    "success": true,
    "data": {}
}
```

---

## Resource Structure

### AttributeResource

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Attribute ID |
| `name` | string | Translated name |
| `slug` | string | URL slug |
| `values` | array | Attribute values (only when loaded via `show`) |

### AttributeValueResource

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Attribute value ID |
| `value` | string | Translated value |
| `slug` | string | URL slug |
| `attribute_id` | int | Parent attribute ID |

---

## Endpoints — Attributes

### GET /attributes — List Attributes

**Purpose:** List all attributes with their values. Supports pagination, search, sorting, and filtering.

**Method:** `GET`

**URL:** `/attributes`

**Authentication:** Optional

**Permissions:** `view-attributes`

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `limit` | int | 15 | Items per page |
| `order` | string | — | Sort column (`id`, `name`, `slug`, `shop_id`, `created_at`, `updated_at`) |
| `sortedBy` | `asc`/`desc` | `asc` | Sort direction |
| `search` | string | — | Search term (matches `name` via LIKE) |

**Example URLs:**
```
GET /attributes?limit=10
GET /attributes?search=size
GET /attributes?order=name&sortedBy=desc
GET /attributes?limit=20&order=created_at&sortedBy=desc
GET /attributes?search=color&limit=5
```

**Business Logic:**
1. Applies optional `order`/`sortedBy` sorting
2. `RequestCriteria` handles `search`, `shop_id` and other field filters automatically
3. Eager-loads `values` relation
4. Returns paginated `AttributeResource` collection

**Success Response (200):**
```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Size",
            "slug": "size"
        },
        {
            "id": 2,
            "name": "Color",
            "slug": "color"
        }
    ],
    "page": 1,
    "current_page": 1,
    "from": 1,
    "to": 2,
    "last_page": 1,
    "path": "http://localhost/api/attributes",
    "per_page": 15,
    "total": 2,
    "next_page_url": "",
    "prev_page_url": "",
    "last_page_url": "http://localhost/api/attributes?page=1",
    "first_page_url": "http://localhost/api/attributes?page=1"
}
```

**Error Responses:**
| Status | Condition |
|--------|-----------|
| 401 | Unauthenticated |
| 403 | Missing `view-attributes` permission |

---

### POST /attributes — Create Attribute

**Purpose:** Create a new attribute with optional values.

**Method:** `POST`

**URL:** `/attributes`

**Authentication:** Required

**Permissions:** `create-attribute`

**Request Body (JSON):**

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `name` | object | **Yes** | Translatable array |
| `name.en` | string | **Yes** | `string`, `min:2`, `max:50`, unique translation |
| `name.ar` | string | **Yes** | `string`, `min:2`, `max:50`, unique translation |
| `values` | array | No | Array of value objects |
| `values.*` | object | No | Value object |
| `values.*.value` | object | No | Translatable value array |
| `values.*.value.*` | string | No | `string`, `min:2`, `max:50` |

**Example Request:**
```json
{
    "name": {
        "en": "Size",
        "ar": "حجم"
    },
    "values": [
        { "value": { "en": "Small", "ar": "صغير" } },
        { "value": { "en": "Medium", "ar": "متوسط" } },
        { "value": { "en": "Large", "ar": "كبير" } }
    ]
}
```

**Business Logic:**
1. Validates via `AttributeRequest`
2. Generates slug from English name via `Sluggable` trait
3. Creates the attribute
4. If `values` provided, creates each `AttributeValue` linked to the attribute (generates slug per value)
5. Uses database transaction for atomicity
6. Returns attribute with loaded values

**Success Response (201):**
```json
{
    "status": 201,
    "message": "Attribute created successfully",
    "success": true,
    "data": {
        "id": 1,
        "name": "Size",
        "slug": "size",
        "values": [
            { "id": 1, "value": "Small", "slug": "small", "attribute_id": 1 },
            { "id": 2, "value": "Medium", "slug": "medium", "attribute_id": 1 },
            { "id": 3, "value": "Large", "slug": "large", "attribute_id": 1 }
        ]
    }
}
```

**Error Responses:**
| Status | Condition |
|--------|-----------|
| 401 | Unauthenticated |
| 403 | Missing `create-attribute` permission |
| 422 | Validation failure |

---

### GET /attributes/{id} — Show Attribute

**Purpose:** Fetch a single attribute by ID or slug with its values.

**Method:** `GET`

**URL:** `/attributes/{id}`

**Authentication:** Optional

**Permissions:** `view-attributes`

**Business Logic:**
1. If `{id}` is numeric, finds by `id`; otherwise finds by `slug`
2. Eager-loads `values` relation
3. Returns attribute resource

**Success Response (200):**
```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": {
        "id": 1,
        "name": "Size",
        "slug": "size",
        "values": [
            { "id": 1, "value": "Small", "slug": "small", "attribute_id": 1 },
            { "id": 2, "value": "Medium", "slug": "medium", "attribute_id": 1 },
            { "id": 3, "value": "Large", "slug": "large", "attribute_id": 1 }
        ]
    }
}
```

**Error Responses:**
| Status | Condition |
|--------|-----------|
| 401 | Unauthenticated |
| 403 | Missing `view-attributes` permission |
| 404 | Attribute not found |

---

### PUT /attributes/{id} — Update Attribute

**Purpose:** Update an attribute's name and/or replace its values.

**Method:** `PUT`

**URL:** `/attributes/{id}`

**Authentication:** Required

**Permissions:** `update-attribute`

**Request Body (JSON):**

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `name` | object | No | Translatable array |
| `name.en` | string | No | `string`, `min:2`, `max:50`, unique translation (ignores self) |
| `name.ar` | string | No | `string`, `min:2`, `max:50`, unique translation (ignores self) |
| `values` | array | No | Array of value objects (replaces all existing values) |
| `values.*` | object | No | Value object |
| `values.*.value` | object | No | Translatable value array |

**Example Request:**
```json
{
    "name": {
        "en": "Size Updated",
        "ar": "حجم محدث"
    },
    "values": [
        { "value": { "en": "XS", "ar": "صغير جداً" } },
        { "value": { "en": "XL", "ar": "كبير جداً" } }
    ]
}
```

**Business Logic:**
1. Validates via `AttributeRequest`
2. Finds attribute by ID
3. Updates attribute fields with new slug
4. If `values` provided, **deletes all existing values** and creates new ones from the input
5. Returns updated attribute with values

**Success Response (200):**
```json
{
    "status": 200,
    "message": "Attribute updated successfully",
    "success": true,
    "data": {
        "id": 1,
        "name": "Size Updated",
        "slug": "size-updated",
        "values": [
            { "id": 4, "value": "XS", "slug": "xs", "attribute_id": 1 },
            { "id": 5, "value": "XL", "slug": "xl", "attribute_id": 1 }
        ]
    }
}
```

**Error Responses:**
| Status | Condition |
|--------|-----------|
| 401 | Unauthenticated |
| 403 | Missing `update-attribute` permission |
| 404 | Attribute not found |
| 422 | Validation failure |

---

### DELETE /attributes/{id} — Delete Attribute

**Purpose:** Delete an attribute and its values.

**Method:** `DELETE`

**URL:** `/attributes/{id}`

**Authentication:** Required

**Permissions:** `delete-attribute`

**Business Logic:**
1. Finds attribute by ID
2. Deletes the record (cascades to attribute_values)
3. Returns success message

**Success Response (200):**
```json
{
    "status": 200,
    "message": "Attribute deleted successfully",
    "success": true
}
```

**Error Responses:**
| Status | Condition |
|--------|-----------|
| 401 | Unauthenticated |
| 403 | Missing `delete-attribute` permission |
| 404 | Attribute not found |

---

### POST /import-attributes — Import Attributes via CSV

**Purpose:** Import attributes with values from a CSV file.

**Method:** `POST`

**URL:** `/import-attributes`

**Authentication:** Required

**Permissions:** `super_admin` or `store_owner`

**Business Logic:**
1. Uploads CSV file to `public/csv-files/`
2. Parses CSV into array
3. For each row, creates/finds attribute by name + shop_id
4. Parses comma-separated `values` column and creates attribute values
5. Uses `firstOrCreate` to avoid duplicates

---

### GET /export-attributes/{shop_id} — Export Attributes as CSV

**Method:** `GET`

**URL:** `/export-attributes/{shop_id}`

**Authentication:** Required

**Description:** Exports all attributes for a shop as a CSV download.

---

## Endpoints — Attribute Values

### GET /attribute-values — List Attribute Values

**Purpose:** List all attribute values with their parent attribute. Supports pagination, search, sorting, and filtering.

**Method:** `GET`

**URL:** `/attribute-values`

**Authentication:** Optional

**Permissions:** `view-attributes`

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `limit` | int | 15 | Items per page |
| `order` | string | — | Sort column (`id`, `value`, `attribute_id`, `slug`, `created_at`, `updated_at`) |
| `sortedBy` | `asc`/`desc` | `asc` | Sort direction |
| `search` | string | — | Search term (matches `value` via LIKE) |
| `language` | string | — | Filter by language |

**Example URLs:**
```
GET /attribute-values?limit=10
GET /attribute-values?search=small
GET /attribute-values?order=value&sortedBy=desc
GET /attribute-values?limit=20&order=created_at&sortedBy=desc
GET /attribute-values?search=large&language=en
```

**Business Logic:**
1. Applies optional `order`/`sortedBy` sorting
2. `RequestCriteria` handles `search`, `shop_id`, `language` and other field filters automatically
3. Eager-loads `attribute` relation
4. Returns paginated `AttributeValueResource` collection

**Success Response (200):**
```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": [
        {
            "id": 1,
            "value": "Small",
            "slug": "small",
            "attribute_id": 1
        },
        {
            "id": 2,
            "value": "Medium",
            "slug": "medium",
            "attribute_id": 1
        }
    ],
    "page": 1,
    "current_page": 1,
    "from": 1,
    "to": 2,
    "last_page": 1,
    "path": "http://localhost/api/attribute-values",
    "per_page": 15,
    "total": 2,
    "next_page_url": "",
    "prev_page_url": "",
    "last_page_url": "http://localhost/api/attribute-values?page=1",
    "first_page_url": "http://localhost/api/attribute-values?page=1"
}
```

---

### POST /attribute-values — Create Attribute Value

**Purpose:** Create a new attribute value.

**Method:** `POST`

**URL:** `/attribute-values`

**Authentication:** Required

**Permissions:** `create-attribute`

**Request Body (JSON):**

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `value` | object | **Yes** | Translatable array |
| `value.en` | string | **Yes** | `string`, `max:255` |
| `value.ar` | string | **Yes** | `string`, `max:255` |
| `attribute_id` | int | **Yes** | `exists:attributes,id` |

**Example Request:**
```json
{
    "value": {
        "en": "red",
        "ar": "احمر"
    },
    "attribute_id": 1
}
```

**Business Logic:**
1. Validates via `AttributeValueRequest`
2. Creates attribute value with translatable `value` field
3. Returns created resource

**Success Response (201):**
```json
{
    "status": 201,
    "message": "Attribute value created successfully",
    "success": true,
    "data": {
        "id": 6,
        "value": {
            "en": "red",
            "ar": "احمر"
        },
        "slug": "red",
        "attribute_id": 1
    }
}
```

**Error Responses:**
| Status | Condition |
|--------|-----------|
| 401 | Unauthenticated |
| 403 | Missing `create-attribute` permission or shop access |
| 422 | Validation failure |

---

### GET /attribute-values/{id} — Show Attribute Value

**Purpose:** Fetch a single attribute value.

**Method:** `GET`

**URL:** `/attribute-values/{id}`

**Authentication:** Optional

**Permissions:** `view-attributes`

**Business Logic:**
1. Finds by ID with `attribute` relation
2. Returns resource

**Success Response (200):**
```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": {
        "id": 6,
        "value": {
            "en": "Extra Large",
            "ar": "كبير جداً"
        },
        "slug": "extra-large",
        "attribute_id": 1
    }
}
```

**Purpose:** Update an attribute value.

**Method:** `PUT`

**URL:** `/attribute-values/{id}`

**Authentication:** Required

**Permissions:** `update-attribute`

**Request Body (JSON):**

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `value` | object | No | Translatable array |
| `value.en` | string | No | `string`, `max:255` |
| `value.ar` | string | No | `string`, `max:255` |
| `attribute_id` | int | No | `exists:attributes,id` |

**Example Request:**
```json
{
    "value": {
        "en": "XXL",
        "ar": "اكس اكس ال"
    }
}
```

**Business Logic:**
1. Checks `hasPermission` for the user + shop
2. Updates the attribute value
3. Returns fresh resource

**Success Response (200):**
```json
{
    "status": 200,
    "message": "Attribute value updated successfully",
    "success": true,
    "data": {
        "id": 6,
        "value": {
            "en": "XXL",
            "ar": "اكس اكس ال"
        },
        "slug": "xxl",
        "attribute_id": 1
    }
}
```

---

### DELETE /attribute-values/{id} — Delete Attribute Value

**Purpose:** Delete an attribute value.

**Method:** `DELETE`

**URL:** `/attribute-values/{id}`

**Authentication:** Required

**Permissions:** `delete-attribute`

**Business Logic:**
1. Retrieves the attribute value's parent attribute `shop_id`
2. Checks `hasPermission` for the user + that shop
3. Deletes the attribute value
4. Returns success message

**Success Response (200):**
```json
{
    "status": 200,
    "message": "Attribute value deleted successfully",
    "success": true
}
```

**Error Responses:**
| Status | Condition |
|--------|-----------|
| 401 | Unauthenticated |
| 403 | Missing `delete-attribute` permission or shop access |
| 404 | Attribute value not found |

---

## Route Definitions

```php
// Public routes (no auth)
Route::apiResource('attributes', AttributeController::class, ['only' => ['index', 'show']]);
Route::apiResource('attribute-values', AttributeValueController::class, ['only' => ['index', 'show']]);

// Import/Export (auth)
Route::post('import-attributes', [AttributeController::class, 'importAttributes']);
Route::get('export-attributes/{shop_id}', [AttributeController::class, 'exportAttributes']);

// Admin routes (auth + permissions)
Route::apiResource('attributes', AttributeController::class, ['only' => ['store', 'update', 'destroy']]);
Route::apiResource('attribute-values', AttributeValueController::class, ['only' => ['store', 'update', 'destroy']]);
```

Source: `packages/marvel/src/Rest/Routes.php`

---

## Permissions Map

| Permission Enum | String | Applied To |
|----------------|--------|------------|
| `VIEW_ATTRIBUTES` | `view-attributes` | `AttributeController@index`, `AttributeController@show`, `AttributeValueController@index`, `AttributeValueController@show` |
| `CREATE_ATTRIBUTE` | `create-attribute` | `AttributeController@store`, `AttributeValueController@store` |
| `UPDATE_ATTRIBUTE` | `update-attribute` | `AttributeController@update`, `AttributeValueController@update` |
| `DELETE_ATTRIBUTE` | `delete-attribute` | `AttributeController@destroy`, `AttributeValueController@destroy` |

---

## Model Features

- **Translatable:** `Attribute.name`, `AttributeValue.value` (Spatie `HasTranslations`)
- **Sluggable:** Auto-generated slugs via `Cviebrock\EloquentSluggable` (from English name/value)
- **Relations:**
  - `Attribute hasMany AttributeValue`
  - `AttributeValue belongsTo Attribute`
  - `AttributeValue belongsToMany Product` via `attribute_product` pivot
  - `AttributeValue belongsToMany ProductVariant` via `attribute_product` pivot

---

## Dependencies

| Class | Type | File |
|-------|------|------|
| `AttributeController` | Controller | `packages/marvel/src/Http/Controllers/AttributeController.php` |
| `AttributeValueController` | Controller | `packages/marvel/src/Http/Controllers/AttributeValueController.php` |
| `AttributeRepository` | Repository | `packages/marvel/src/Database/Repositories/AttributeRepository.php` |
| `AttributeValueRepository` | Repository | `packages/marvel/src/Database/Repositories/AttributeValueRepository.php` |
| `Attribute` | Model | `packages/marvel/src/Database/Models/Attribute.php` |
| `AttributeValue` | Model | `packages/marvel/src/Database/Models/AttributeValue.php` |
| `AttributeProduct` | Pivot Model | `packages/marvel/src/Database/Models/AttributeProduct.php` |
| `AttributeResource` | Resource | `packages/marvel/src/Http/Resources/AttributeResource.php` |
| `AttributeValueResource` | Resource | `packages/marvel/src/Http/Resources/AttributeValueResource.php` |
| `AttributeRequest` | Form Request | `packages/marvel/src/Http/Requests/AttributeRequest.php` |
| `AttributeValueRequest` | Form Request | `packages/marvel/src/Http/Requests/AttributeValueRequest.php` |
| `Permission` | Enum | `packages/marvel/src/Enums/Permission.php` |

---

## Translations

| Key | English | Arabic |
|-----|---------|--------|
| `MESSAGE.ATTRIBUTE_CREATED_SUCCESSFULLY` | Attribute created successfully | تم إنشاء السمة بنجاح |
| `MESSAGE.ATTRIBUTE_UPDATED_SUCCESSFULLY` | Attribute updated successfully | تم تحديث السمة بنجاح |
| `MESSAGE.ATTRIBUTE_DELETED_SUCCESSFULLY` | Attribute deleted successfully | تم حذف السمة بنجاح |
| `MESSAGE.ATTRIBUTE_VALUE_CREATED_SUCCESSFULLY` | Attribute value created successfully | تم إنشاء قيمة السمة بنجاح |
| `MESSAGE.ATTRIBUTE_VALUE_UPDATED_SUCCESSFULLY` | Attribute value updated successfully | تم تحديث قيمة السمة بنجاح |
| `MESSAGE.ATTRIBUTE_VALUE_DELETED_SUCCESSFULLY` | Attribute value deleted successfully | تم حذف قيمة السمة بنجاح |

---

## Notes

- Attribute values are **fully replaced** on update — send the complete desired list
- Update uses `delete()` on all existing values before recreating; there is no partial sync
- The `slug` is auto-generated from the English translation and is read-only
- Attribute names are unique per translation via `UniqueTranslationRule`
- CSV import expects columns: `name`, `values` (comma-separated), `shop_id`

---

## Logic Review Findings

| Issue | Severity | Status |
|-------|----------|--------|
| `AttributeValueController` lacked `ApiResponse` trait and returned raw models | High | **Fixed** |
| `AttributeValueController` had no permission middleware | High | **Fixed** |
| `AttributeController::deleteAttribute` missing `true` success flag | Low | **Fixed** |
| `AttributeValueResource` had `slug` commented out | Low | **Fixed** |
| No `ATTRIBUTE_VALUE_*` constants defined | Medium | **Fixed** |
| No `ATTRIBUTE_VALUE_*` translations in en/ar message files | Medium | **Fixed** |
| Missing public routes for `attribute-values` index/show | Medium | **Fixed** |
