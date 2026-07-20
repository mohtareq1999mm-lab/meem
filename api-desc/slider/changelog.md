# Slider Module — Changelog

## [1.0.0] — 2026-07-20

### Added
- Slider CRUD: full create, read, update, delete (soft) with permissions
- Public API: list active sliders + get by slug with products
- Status toggle endpoint (PATCH /change-status)
- Reorder endpoint (PUT /reorder)
- Translatable titles (en/ar)
- Media uploads (desktop + mobile images)
- Product associations via slider_product pivot
- Soft deletes support
- Spatie Sortable integration for order management
- MediaCleanupObserver for media cleanup on force delete
- Excel Import/Export support for bulk slider-product associations
- ~47 tests in SliderApiTest.php
- Comprehensive documentation (`api-desc/slider/`)

### Changed
- N/A (initial comprehensive documentation)

### Fixed
- N/A (initial comprehensive documentation)

## Known Issues

1. **Missing `sliders` table migration** — No migration file exists for production deployment.
2. **Duplicate route registrations** — `apiResource('sliders')` registered 3 times in Routes.php.
3. **Inconsistent media collection names** — Create uses `slider-image-*`, update uses `sliders-*`.
4. **Media preserved on soft delete** — Media files are only cleaned on force delete (by design).
