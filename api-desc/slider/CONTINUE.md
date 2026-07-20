# Slider Module — Session Continuation Prompt

Copy the block below into a new opencode session to continue the slider pipeline with the full context:

---

```
You are continuing work on the meem-commerce Laravel project at D:\meem-commerce.

## Module: Sliders (slider)

## What Has Been Done (13 Documentation Files)
All files in api-desc/slider/ are complete:
- README.md, backend.md, api.md, database.md, flow.md
- bug-report.md, changelog.md, frontend.md
- jira.md, jira-frontend.md, qa.md, test-cases.md
- CONTINUE.md

## Architecture Summary
- Standard CRUD module: Controller → Repository → Model
- Translatable: title (en/ar via Spatie HasTranslations)
- Media: desktop + mobile images via Spatie Media Library
- Soft deletes via Laravel SoftDeletes trait
- Sortable via Spatie SortableTrait (order column)
- Product associations via BelongsToMany (slider_product pivot)
- Auto-generated slug from English title via saving event
- ~47 tests in SliderApiTest.php

## Key Files
- Admin Controller: packages/marvel/src/Http/Controllers/SliderController.php
- Public Controller: app/Http/Controllers/Api/General/SliderController.php
- Model: packages/marvel/src/Database/Models/Slider.php (HasTranslations, InteractsWithMedia, SoftDeletes, SortableTrait)
- Repository: packages/marvel/src/Database/Repositories/SliderRepository.php
- Service: app/Services/General/SliderService.php
- Create Request: packages/marvel/src/Http/Requests/SliderCreateRequest.php
- Update Request: packages/marvel/src/Http/Requests/SliderUpdateRequest.php
- Admin Resource: packages/marvel/src/Http/Resources/SliderResource.php
- Public Resource: app/Http/Resources/Slider/SliderResource.php
- Routes Admin: packages/marvel/src/Rest/Routes.php (lines 164-165, 201-202, 204)
- Routes Public: routes/api.php (lines 50-51)
- Observer: app/Observers/MediaCleanupObserver.php (registered in EventServiceProvider)
- Import: packages/marvel/src/Imports/Sheets/SlidersSheetImport.php
- Export: packages/marvel/src/Exports/Sheets/SlidersSheetExport.php
- Tests: tests/Feature/SliderApiTest.php (~47 tests)
- Seeder: database/seeders/SliderSeeder.php (10 sliders)
- Seeder: database/seeders/SliderProductSeeder.php

## Known Bugs (api-desc/slider/bug-report.md)
- BUG-SL-001: Missing sliders table migration (OPEN - HIGH)
- BUG-SL-002: Duplicate route registrations (OPEN)
- BUG-SL-003: Inconsistent media collection names (OPEN)
- BUG-SL-004: MediaCleanupObserver skips on soft delete (BY DESIGN)

## Backend Jira Tasks (api-desc/slider/jira.md)
1. Add missing sliders table migration (OPEN)
2. Deduplicate route registrations (OPEN)
3. Unify media collection names (OPEN)
4. Run full test suite (DONE)
5. Verify public API response consistency (OPEN)
6. Add slider-product pivot validation (OPEN)

## Frontend Jira Tasks (api-desc/slider/jira-frontend.md)
1. Admin slider listing table with reorder
2. Admin create/edit form with image upload
3. Drag-and-drop reorder
4. Homepage banner carousel
5. Slider detail page with products
6. Status toggle UI
7. Delete confirmation dialog
8. Loading/empty/error states
9. Multilingual translatable fields

## Next Actions (Recommended Order)
1. Create sliders table migration: php artisan make:migration create_sliders_table
2. Deduplicate route registrations in Rest/Routes.php
3. Unify media collection names in SliderRepository
4. Run test suite: php vendor/bin/phpunit --filter "Slider"
5. Frontend implementation

## Key Technical Constraints
- PREFIX = /api/v1
- Classmap autoloading for packages/marvel
- SQLite in-memory for tests (phpunit.xml)
- PHPUnit 10.0.13, PHP 8.2.28
- Translation constants in packages/marvel/config/constants.php
- English + Arabic translations exist in resources/lang/
- Permission enum values must match middleware strings exactly
- Sliders table schema is only in tests/Concerns/CreatesTestTables.php (not in migrations)
```
