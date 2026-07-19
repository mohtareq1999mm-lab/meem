# Settings Module — Frontend Jira Tasks

## Task 1: Settings Page — Build settings form UI

**Priority:** High
**Component:** Frontend — Admin Settings Page
**Story Points:** 5

**Description:** Build the main settings form page in the admin panel. The form must support all setting fields returned by `GET /api/v1/settings` and submit via `PUT /api/v1/settings`.

**Fields to include:**

| Section | Fields |
|---------|--------|
| Site Info | `site_name` (translatable), `site_desc` (translatable), `meta_desc` (translatable), `site_copy_right` (translatable) |
| Contact | `site_email`, `email_support`, `phone` |
| Social | `facebook`, `instagram`, `linkedin`, `youtube`, `promotion_video_url` |
| Branding | `logo` (image upload, 2MB max), `favicon` (image upload, 2MB max) |
| Shipping | `fast_shipping_page_publish` (toggle) |
| Advanced | `options` (JSON editor or key-value pairs) |

**Acceptance Criteria:**
- [ ] Form loads existing settings on mount (GET)
- [ ] All fields render with correct input types (text, email, url, file, toggle, textarea)
- [ ] File uploads show preview for logo & favicon
- [ ] Translatable fields show language tabs/selectors (en, ar, etc.)
- [ ] Form submits via `PUT /api/v1/settings` as `multipart/form-data`
- [ ] Success response updates the form with new data

---

## Task 2: Settings Page — Loading, empty & error states

**Priority:** High
**Component:** Frontend — Admin Settings Page
**Story Points:** 3

**Description:** Handle all non-happy-path states for the settings page.

**Acceptance Criteria:**
- [ ] **Loading state:** Show skeleton/spinner while fetching settings
- [ ] **Empty/First-time state:** If GET returns 500 (no settings exist), show "No settings configured" message with a setup prompt
- [ ] **Error state:** Display server errors (500), validation errors (422 field-level), and network errors
- [ ] **Update failure:** Show toast/alert when PUT fails
- [ ] **Update success:** Show success toast and refresh displayed data
- [ ] **File upload error:** Show specific error for oversized or invalid format files

---

## Task 3: Settings Page — Logo & Favicon upload with preview

**Priority:** Medium
**Component:** Frontend — Image Upload
**Story Points:** 3

**Description:** Implement image upload for logo and favicon with preview, validation, and fallback.

**Acceptance Criteria:**
- [ ] Show current logo/favicon image preview loaded from `data.logo` / `data.favicon` URLs
- [ ] File picker accepts only: `jpeg`, `png`, `jpg`, `gif`, `svg`
- [ ] File size limited to 2MB (enforced client-side before upload)
- [ ] Show preview immediately after selecting a file, before upload
- [ ] Show placeholder/fallback icon if no logo/favicon URL exists
- [ ] Disable upload button during submission

---

## Task 4: Settings Page — Multilingual translatable fields

**Priority:** Medium
**Component:** Frontend — i18n
**Story Points:** 3

**Description:** Handle translatable fields (`site_name`, `site_desc`, `meta_desc`, `site_copy_right`) that are sent/received as language-keyed objects.

**Request format:**
```json
{
  "site_name": { "en": "My Store", "ar": "متجري" }
}
```

**Response format:** Same structure.

**Acceptance Criteria:**
- [ ] Read current supported languages from app config or environment
- [ ] Show language tabs/tabs for each supported locale
- [ ] Each translatable field shows separate input per language
- [ ] On save, fields are serialized to `{ "en": "...", "ar": "..." }` format
- [ ] On load, each tab shows the correct translation for its language
- [ ] Default language tab is pre-selected based on `DEFAULT_LANGUAGE` config

---

## Task 5: Settings Page — `options` JSON editor

**Priority:** Low
**Component:** Frontend — Advanced Settings
**Story Points:** 2

**Description:** The `options` field is a JSON object that stores arbitrary platform configuration (currency, tax class, etc.). Provide a UI for viewing and editing it.

**Acceptance Criteria:**
- [ ] Render `options` as a key-value editor (add/remove/edit rows)
- [ ] Show known keys with appropriate input types (e.g., currency as dropdown)
- [ ] Fallback to raw JSON editor for unknown keys
- [ ] Validate JSON before submission
- [ ] Handle null/empty options gracefully

---

## Task 6: Settings Page — Fast shipping toggle

**Priority:** Low
**Component:** Frontend — Shipping Settings
**Story Points:** 1

**Description:** The `fast_shipping_page_publish` field is a boolean stored as `"0"` or `"1"` string in the API. Implement a toggle switch.

**Acceptance Criteria:**
- [ ] Render as a toggle/switch component
- [ ] On submit, send `"0"` or `"1"` as string
- [ ] On load, parse `true`/`false`/`1`/`0`/`"1"`/`"0"` correctly
- [ ] Toggle has clear label: "Enable Fast Shipping Page"

---

## Task 7: Public settings consumption — Header/footer integration

**Priority:** Medium
**Component:** Frontend — Public Pages
**Story Points:** 3

**Description:** Consume `GET /api/v1/settings` in the public-facing site (header, footer, SEO meta tags).

**Acceptance Criteria:**
- [ ] Site name renders in header/browser title from `settings.site_name`
- [ ] Social links (facebook, instagram, linkedin, youtube) render in footer
- [ ] Contact info (email, phone) renders in footer/contact page
- [ ] Logo and favicon use `settings.logo` and `settings.favicon` URLs
- [ ] SEO meta tags (meta_desc, site_name) render in `<head>`
- [ ] Copyright text renders in footer from `site_copy_right`
- [ ] Settings are fetched once and cached client-side (localStorage or context)
- [ ] Fallback to hardcoded defaults if settings fail to load
