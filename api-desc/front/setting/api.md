# API Reference — Settings Module (Public API)

---

### GET /api/v1/general/settings

Fetch platform settings. Returns a singleton settings object with site information, SEO metadata, social links, contact details, and media URLs.

**Authentication:** None (public)

**Query Parameters:** None

**Response 200:**
```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": {
    "site_name": "Marvel E-Commerce",
    "site_desc": "Your one-stop shop for everything",
    "meta_desc": "Best online store with great deals",
    "site_copy_right": "© 2026 Marvel. All rights reserved.",
    "logo": "https://cdn.example.com/settings/logo.png",
    "favicon": "https://cdn.example.com/settings/favicon.ico",
    "site_email": "info@marvel.com",
    "email_support": "support@marvel.com",
    "facebook": "https://facebook.com/marvel",
    "instagram": "https://instagram.com/marvel",
    "linkedin": "https://linkedin.com/company/marvel",
    "promotion_video_url": "https://youtube.com/watch?v=xyz",
    "youtube": "https://youtube.com/@marvel",
    "phone": "+1-555-0123",
    "fast_shipping_page_publish": true,
    "options": {
      "currency": "USD",
      "tax_rate": 0.1
    }
  }
}
```

**Quick Test:**
```bash
curl -X GET "http://example.com/api/v1/general/settings" \
  -H "Accept: application/json"
```

**Business Rules:**
- Returns exactly one record (singleton pattern)
- Translatable fields returned in request locale (Accept-Language header)
- Media URLs are returned from Spatie Media Library collections (`logo-setting`, `favicon-setting`)
- `options` field is free-form JSON for arbitrary configuration
- No pagination needed (single object)
