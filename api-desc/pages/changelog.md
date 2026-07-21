# Pages Module — Changelog

## [1.0.0] — 2026-07-21

### Added
- Comprehensive API investigation documentation (`api-desc/pages/`)
- 3 entity groups: ContentPages, Sections, SectionTypes (18 admin + 2 public endpoints)
- ContentPage CRUD with translatable title, slug auto-generation, active toggle
- Section CRUD with sortable ordering (Spatie SortableTrait), translatable title, settings cascade
- SectionType CRUD with route-model binding by `type` string
- Section attach/detach to content pages (batch section ID assignment)
- Section reorder endpoint (ordered array of IDs)
- Section dynamic endpoint generation: `general/{type}?{back params}`
- SectionType settings management (front/back key-value pairs, full replace on update)
- Public API: filtered listing (active sections only)
- Permission-based authorization via 12 Spatie permissions

### Known Issues
1. **Commented-out cache** in public ContentPageController — Cache::remember is commented out, no caching implemented
2. **Section store has dead code** — commented-out SectionTypeService calls for auto-creating types with settings
3. **No pagination on sections index** — returns all sections, could be problematic with many sections
4. **SectionType `show` vs `byType` are identical** — both return grouped settings, potential redundancy
5. **No total count in list responses** — index endpoints return collections without pagination metadata
