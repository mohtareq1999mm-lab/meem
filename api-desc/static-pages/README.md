# Static Pages Module

The Static Pages module manages the **fixed** marketing/legal pages of the store — About Us,
Terms & Conditions, Privacy Policy — plus their structured content sections.

Unlike the dynamic `ContentPage`/`Section` system (Pages module), static pages are seeded and
immutable: the page set and slugs never change at runtime. Admins can only edit the page title /
visibility and manage ordered content sections within each page.

## Key Entities

- **StaticPage** — A fixed, seeded page. Translatable `title`, immutable `slug`, `is_active`
  flag. Has many static sections.
- **StaticSection** — A content block within a page. Translatable `title`, free-form translatable
  `content` (JSON object), sortable `order` scoped per page.

## Key Features

- Fixed page set seeded via `StaticPageSeeder` (`about-us`, `terms-and-conditions`,
  `privacy-policy`), localized EN/AR, all active, idempotent and never destructive
- No create/delete endpoints for pages (405) — pages are never added or removed at runtime
- Full admin CRUD + reorder for sections (5 dedicated permissions)
- Sections are strictly scoped to their page (cross-page access returns 404)
- Translatable titles + free-form translatable content via Spatie Translatable
- Sortable ordering per page via Spatie Eloquent Sortable (`buildSortQuery` scoped by page)
- Public endpoints cached (`static_pages` tag, `md5(fullUrl)` key, models cached not rendered)
- Cache invalidated on every mutation by controller + observers (including reorder, which fires
  no model events)

## Permissions

| Permission | Endpoint(s) |
|------------|-------------|
| `view-static-pages` | Admin list + show |
| `update-static-pages` | Update page |
| `create-static-sections` | Create section |
| `update-static-sections` | Update + reorder sections |
| `delete-static-sections` | Delete section |

## Seeded Pages

| Slug | EN title | AR title |
|------|----------|----------|
| about-us | About Us | من نحن |
| terms-and-conditions | Terms and Conditions | الشروط والأحكام |
| privacy-policy | Privacy Policy | سياسة الخصوصية |
