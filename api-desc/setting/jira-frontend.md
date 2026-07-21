# Settings Module — Frontend Jira Tasks (Admin)

---

## Task 1: Admin Settings Page

**Priority:** High
**Component:** Admin Panel
**Story Points:** 8

**Description:** Build admin settings page with form fields for all settings.

**API Endpoints:**
- `GET /api/v1/settings` — Fetch current settings
- `PUT /api/v1/settings` — Update settings

**Acceptance Criteria:**
- [ ] Form fields for site_name, site_desc, meta_desc, site_copy_right (multilingual)
- [ ] Form fields for site_email, email_support, phone
- [ ] Form fields for facebook, instagram, linkedin, youtube URLs
- [ ] Form field for promotion_video_url
- [ ] Logo and favicon image upload
- [ ] fast_shipping_page_publish toggle
- [ ] minimumOrderAmount number input
- [ ] Fast shipping sub-section (enabled, duration, fee, hours)
- [ ] **Loading state:** Skeleton form
- [ ] **Error state:** Show error alert
- [ ] **Saving state:** Button loading spinner
- [ ] **Success state:** Toast notification

---

## Task 2: Fast Shipping Settings Page

**Priority:** Medium
**Component:** Admin Panel
**Story Points:** 3

**Description:** Fast shipping configuration section within settings page.

**API Endpoints:**
- `GET /api/v1/fast-shipping/settings` — Fetch config
- `PUT /api/v1/fast-shipping/settings` — Update config

**Acceptance Criteria:**
- [ ] Enable/disable toggle
- [ ] Duration minutes input
- [ ] Fee amount input
- [ ] Start/end hour time pickers
