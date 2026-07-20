# Settings Module — Changelog (Public API)

## [1.0.0] — 2026-07-20

### Added
- Comprehensive API investigation documentation (`api-desc/front/setting/`)
- Public settings endpoint: `GET /api/v1/general/settings`
- SettingResource with 17 fields (site info, SEO, social, contact, media, options)
- Translatable fields via Spatie HasTranslations
- Media support for logo and favicon via Spatie Media Library

### Known Issues
1. **No caching on public endpoint** — DB hit on every request (BUG-SETTING-001)
2. **`Settings::getData()` is dead code** — Caching method commented out (BUG-SETTING-002)
3. **No tests for public endpoint** — Only admin CRUD tests exist (BUG-SETTING-003)
4. **Null `options` risk** — No null coalesce on options field (BUG-SETTING-004)
