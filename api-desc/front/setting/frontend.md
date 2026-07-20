# Settings Module — Frontend Integration Guide

---

### 1. GET /api/v1/general/settings — Fetch Platform Settings (Public)

**Purpose:** Retrieve site-wide settings for rendering header, footer, SEO tags, and contact information.

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
      "tax_rate": 0.1,
      "shipping_threshold": 50
    }
  }
}
```

---

## Frontend Usage

### SEO
Use `site_name`, `site_desc`, `meta_desc`, `favicon` for `<title>`, `<meta>`, and favicon tags on every page.

### Header / Footer
Use `logo` for the site logo image, `site_name` for branding text, and social links (`facebook`, `instagram`, `linkedin`, `youtube`) for footer icons.

### Contact
Use `site_email`, `email_support`, `phone` for contact section or "Reach Us" blocks.

### Promotional
Use `promotion_video_url` for hero section video backgrounds or promotional sections.

### Feature Flags
Use `fast_shipping_page_publish` to conditionally show/hide the fast shipping page link. Use `options` for arbitrary feature flags and configuration values.

### State Handling

| State | Behavior |
|-------|----------|
| **Loading** | Skeleton for header/footer placeholders |
| **Success** | Render all settings for header, footer, SEO, contact |
| **Error** | Fall back to hardcoded defaults (site name from env) |
| **Empty (null DB)** | Handle nullable fields with fallback values |
