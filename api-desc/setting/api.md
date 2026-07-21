# API Reference — Settings Module (Admin API)

---

### GET /api/v1/settings

Fetch platform settings. Returns full settings object with site info, SEO, social, contact, media, options.

**Authentication:** Public (no auth required)

**Query Parameters:** None

**Response 200:**
```json
{
    "status": 200,
    "message": "تم جلب البيانات بنجاح",
    "success": true,
    "data": {
        "site_name": "موقعي",
        "site_desc": "هذا هو وصف الموقع.",
        "meta_desc": "الوصف التعريفي للموقع.",
        "site_copy_right": "© 2026 جميع الحقوق محفوظة.",
        "logo": "",
        "favicon": "",
        "site_email": "info@example.com",
        "email_support": "support@example.com",
        "facebook": "https://facebook.com/mywebsite",
        "instagram": "https://instagram.com/mywebsite",
        "linkedin": "https://linkedin.com/company/mywebsite",
        "promotion_video_url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
        "youtube": "https://youtube.com/@mywebsite",
        "phone": "+201001234567",
        "fast_shipping_page_publish": 1,
        "minimumOrderAmount": 100,
        "options": {
            "minimumOrderAmount": 100,
            "fast_shipping": {
                "enabled": true,
                "duration_minutes": 120,
                "fee": 0,
                "start_hour": "08:00",
                "end_hour": "22:00"
            }
        }
    }
}
```

---

### PUT /api/v1/settings

Update platform settings. Replaces all provided fields. Super admin only.

**Authentication:** Sanctum token with `update-settings` permission

**Request Body:**
```json
{
    "site_name": {"en": "Name", "ar": "الاسم"},
    "site_desc": {"en": "Description", "ar": "الوصف"},
    "meta_desc": {"en": "Meta", "ar": "الوصف التعريفي"},
    "site_copy_right": {"en": "Copyright", "ar": "حقوق النشر"},
    "site_email": "admin@example.com",
    "email_support": "support@example.com",
    "facebook": "https://facebook.com/...",
    "instagram": "https://instagram.com/...",
    "linkedin": "https://linkedin.com/...",
    "youtube": "https://youtube.com/...",
    "phone": "+201001234567",
    "fast_shipping_page_publish": "1",
    "options": {
        "minimumOrderAmount": 100,
        "fast_shipping": {
            "enabled": true,
            "duration_minutes": 120,
            "fee": 0,
            "start_hour": "08:00",
            "end_hour": "22:00"
        }
    }
}
```

**Validation Rules:**
| Field | Rules |
|-------|-------|
| site_name | required, array |
| site_name.* | required, string, min:3, max:200 |
| site_desc | required, array |
| site_desc.* | required, string, min:3, max:2000 |
| meta_desc | required, array |
| meta_desc.* | required, string, min:3, max:2000 |
| site_copy_right | required, array |
| site_copy_right.* | required, string, min:3, max:200 |
| logo | sometimes, image, mimes:jpeg,png,jpg,gif,svg, max:2048 |
| favicon | sometimes, image, mimes:jpeg,png,jpg,gif,svg, max:2048 |
| site_email | required, email |
| email_support | required, email |
| facebook | required, url |
| instagram | required, url |
| linkedin | required, url |
| promotion_video_url | sometimes, url |
| youtube | required, url |
| phone | required, string |
| fast_shipping_page_publish | required, in:0,1 |
| options | sometimes, array |

**Response 200:**
```json
{
    "status": 200,
    "message": "Settings updated successfully",
    "success": true,
    "data": { ... }
}
```

---

### GET /api/v1/fast-shipping/settings

Fetch fast shipping configuration.

**Authentication:** Sanctum token with `view-fast-shipping` permission

**Response 200:**
```json
{
    "status": 200,
    "message": "Data fetched successfully",
    "success": true,
    "data": {
        "enabled": true,
        "duration_minutes": 120,
        "fee": 30,
        "start_hour": "08:00",
        "end_hour": "22:00"
    }
}
```

**Data Source:** `settings.options.fast_shipping` JSON — cached for 1 hour (`Cache::remember('fast_shipping_settings', 3600, ...)`)

---

### PUT /api/v1/fast-shipping/settings

Update fast shipping configuration.

**Authentication:** Sanctum token with `update-fast-shipping` permission

**Request Body:**
```json
{
    "enabled": true,
    "duration_minutes": 120,
    "fee": 30,
    "start_hour": "08:00",
    "end_hour": "22:00"
}
```

**Validation Rules:**
| Field | Rules |
|-------|-------|
| enabled | sometimes, boolean |
| duration_minutes | sometimes, integer, min:1, max:1440 |
| fee | sometimes, numeric, min:0 |
| start_hour | sometimes, string, date_format:H:i |
| end_hour | sometimes, string, date_format:H:i |

**Response 200:**
```json
{
    "status": 200,
    "message": "Fast shipping settings updated successfully",
    "success": true
}
```

**Cache:** Cleared on update (`Cache::forget('fast_shipping_settings')`)
