# Database — Static Pages Module

## Migrations

- `2026_08_18_000001_create_static_pages_table`
- `2026_08_18_000002_create_static_sections_table`

## static_pages

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK, auto-increment | |
| slug | string | UNIQUE, NOT NULL | Immutable, seeded (`about-us`, `terms-and-conditions`, `privacy-policy`) |
| title | json | NOT NULL | Translatable map `{ "en": "...", "ar": "..." }` |
| is_active | boolean | default true | |
| created_at / updated_at | timestamp | | |

## static_sections

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK, auto-increment | |
| static_page_id | bigint | FK → static_pages.id, NOT NULL, `cascadeOnDelete`, indexed | Owns the section; ordering is scoped by this |
| title | json | NOT NULL | Translatable map |
| content | json | nullable | Translatable free-form map `{ "en": {...}, "ar": {...} }` |
| order | integer | default 1 | Sortable position within the page |
| created_at / updated_at | timestamp | | |

Indexes: `static_pages.slug` (unique), `static_sections.static_page_id` (regular index).

## Relationships

- `StaticPage` hasMany `StaticSection` (ordered by `order` ASC) — `StaticPage::staticSections()`
- `StaticSection` belongsTo `StaticPage` — `StaticSection::staticPage()`

## Constraints / Integrity

- `static_page_id` is NOT NULL → a section can never be orphaned.
- `cascadeOnDelete` → deleting a page removes its sections (not reachable via API, but enforced
  at DB level for direct deletes).
- No unique constraint on `order` — reorder/delete may leave gaps; new sections are placed at
  `max(order) + 1`.

## Seed Data

`StaticPageSeeder` (`firstOrCreate` by `slug`):

| slug | title.en | title.ar | is_active |
|------|----------|----------|-----------|
| about-us | About Us | من نحن | 1 |
| terms-and-conditions | Terms and Conditions | الشروط والأحكام | 1 |
| privacy-policy | Privacy Policy | سياسة الخصوصية | 1 |

The seeder is idempotent and **never** overwrites existing titles/`is_active`, never creates,
updates or deletes sections.
