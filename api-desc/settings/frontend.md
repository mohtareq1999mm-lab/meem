# Settings Module — Frontend Integration Guide

## Endpoints

### 1. GET /api/v1/settings — Fetch Platform Settings

**Purpose:** Retrieve all public platform settings (site name, contact info, social links, media URLs).

**Authentication:** None (public)

**Response:**
```json
{
  "success": true,
  "message": "Data fetched successfully",
  "data": {
    "site_name": "My Store",
    "site_desc": "A great store description",
    "meta_desc": "SEO meta description",
    "site_copy_right": "2024 My Store",
    "logo": "https://cdn.example.com/logo.png",
    "favicon": "https://cdn.example.com/favicon.png",
    "site_email": "admin@store.com",
    "email_support": "support@store.com",
    "facebook": "https://facebook.com/store",
    "instagram": "https://instagram.com/store",
    "linkedin": "https://linkedin.com/store",
    "promotion_video_url": "https://youtube.com/watch?v=xxx",
    "youtube": "https://youtube.com/store",
    "phone": "+1234567890",
    "fast_shipping_page_publish": true,
    "options": {
      "currency": "USD",
      "taxClass": "1"
    }
  }
}
```

**Error Response:** 500 if no settings exist.

### 2. PUT /api/v1/settings — Update Platform Settings

**Purpose:** Update platform-wide settings (requires `update-settings` permission).

**Authentication:** Required (Sanctum)

**Permission:** `update-settings`

**Request:**
```
Content-Type: multipart/form-data
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `site_name[en]` | string | Yes | Site name (min:3, max:200) |
| `site_desc[en]` | string | Yes | Site description (min:3, max:2000) |
| `meta_desc[en]` | string | Yes | Meta description (min:3, max:2000) |
| `site_copy_right[en]` | string | Yes | Copyright text (min:3, max:200) |
| `logo` | file | Yes | JPEG/PNG/GIF/SVG, max 2MB |
| `favicon` | file | Yes | JPEG/PNG/GIF/SVG, max 2MB |
| `site_email` | email | Yes | Admin email |
| `email_support` | email | Yes | Support email |
| `facebook` | url | Yes | Facebook page URL |
| `instagram` | url | Yes | Instagram URL |
| `linkedin` | url | Yes | LinkedIn URL |
| `youtube` | url | Yes | YouTube URL |
| `phone` | string | Yes | Contact phone |
| `fast_shipping_page_publish` | string | Yes | `"0"` or `"1"` |
| `promotion_video_url` | url | No | Promo video URL |
| `options` | object | No | Additional options JSON |

**Success Response (200):**
```json
{
  "success": true,
  "message": "Settings updated successfully",
  "data": {
    "site_name": "New Name",
    "site_desc": "...",
    "fast_shipping_page_publish": true,
    "options": {
      "currency": "USD"
    }
  }
}
```

**Error Responses:**
- `401` — Unauthenticated
- `403` — Forbidden (missing `update-settings` permission)
- `422` — Validation failed
- `500` — Settings update failed

### 3. GET /api/v1/general/settings — Public Settings (App-level)

**Purpose:** Alternative public endpoint for frontend settings.

**Authentication:** None (public)

**Response:** Same structure as `/api/v1/settings`.

## Frontend Usage

### Loading State
```js
// Example fetch
const response = await fetch('/api/v1/settings');
if (!response.ok) {
  // Show loading/error state
}
const settings = await response.json();
```

### Empty State
If no settings exist (first-time setup), the GET endpoint returns 500. The frontend should handle this gracefully:
- Show default/fallback values
- Show a setup prompt for admins

### Error State
- **422:** Validation errors — field-level error messages
- **500:** Server error — settings record may not exist

## Key Considerations

1. **Multipart form data** is required for PUT (due to file uploads for logo/favicon)
2. **Translatable fields** (`site_name`, `site_desc`, `meta_desc`, `site_copy_right`) are sent as nested objects: `{ "en": "value", "ar": "القيمة" }`
3. **Cache:** Settings are cached for 24 hours. If you update settings via the public `general/settings` route, cache may serve stale data.
4. **Default language:** The `DEFAULT_LANGUAGE` constant (`shop.default_language` config) determines the default locale. Current value: `en`. App locale: `ar`.
5. **Logo/Favicon** URLs come from the Spatie Media Library. Uploaded images replace previous ones.
