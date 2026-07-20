# Slider Module

## Overview

The Slider module manages promotional slider/banner images on the e-commerce platform. It provides two API surfaces:

- **Admin API** (`/api/v1/sliders`) — Full CRUD + reorder + status toggle, protected by permissions
- **Public API** (`/api/v1/general/sliders`) — Read-only listing of active sliders, no authentication required

Sliders support translatable titles (en/ar), desktop + mobile image uploads, product associations, soft deletes, and drag-and-drop reordering via the Spatie Sortable trait. Products can be filtered by slider slug.

## Key Files

| Layer | File |
|-------|------|
| Admin Controller | `packages/marvel/src/Http/Controllers/SliderController.php` |
| Public Controller | `app/Http/Controllers/Api/General/SliderController.php` |
| Repository | `packages/marvel/src/Database/Repositories/SliderRepository.php` |
| Model | `packages/marvel/src/Database/Models/Slider.php` |
| Admin Resource | `packages/marvel/src/Http/Resources/SliderResource.php` |
| Public Resource | `app/Http/Resources/Slider/SliderResource.php` |
| Create Request | `packages/marvel/src/Http/Requests/SliderCreateRequest.php` |
| Update Request | `packages/marvel/src/Http/Requests/SliderUpdateRequest.php` |
| Slider Service | `app/Services/General/SliderService.php` |
| Admin Routes | `packages/marvel/src/Rest/Routes.php` |
| Public Routes | `routes/api.php` |
| Permissions | `packages/marvel/src/Enums/Permission.php` |
| Pivot Migration | `database/migrations/2026_06_17_000003_create_slider_product_table.php` |
| Seeder | `database/seeders/SliderSeeder.php` |
| Seeder | `database/seeders/SliderProductSeeder.php` |
| Observer | `app/Observers/MediaCleanupObserver.php` |
| Tests | `tests/Feature/SliderApiTest.php` |
| Import | `packages/marvel/src/Imports/Sheets/SlidersSheetImport.php` |
| Export | `packages/marvel/src/Exports/Sheets/SlidersSheetExport.php` |

## Dependencies

- **Spatie Translatable** (`HasTranslations`) — bilingual title (en/ar)
- **Spatie Media Library** (`InteractsWithMedia`) — desktop + mobile image management
- **Spatie Eloquent Sortable** (`SortableTrait`) — drag-and-drop reordering via `order` column
- **SoftDeletes** — soft delete support
- **CodeZero UniqueTranslation** — unique validation per locale

## Permissions

| Permission | Required For |
|------------|-------------|
| `view-slider` | GET /sliders, GET /sliders/{id} |
| `create-slider` | POST /sliders |
| `update-slider` | PUT /sliders/{id}, PUT /sliders/reorder, PATCH /sliders/change-status |
| `delete-slider` | DELETE /sliders/{id} |

## Routes

### Admin

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/v1/sliders` | List sliders (paginated, filterable) |
| POST | `/api/v1/sliders` | Create slider (with images + product associations) |
| GET | `/api/v1/sliders/{id}` | Show slider by ID |
| PUT | `/api/v1/sliders/{id}` | Update slider |
| DELETE | `/api/v1/sliders/{id}` | Soft delete slider |
| PATCH | `/api/v1/sliders/change-status` | Toggle slider status |
| PUT | `/api/v1/sliders/reorder` | Reorder sliders (sorted ID array) |

### Public

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/v1/general/sliders` | List active sliders (optional slug query param) |
| GET | `/api/v1/general/sliders/{slug}` | Get slider by slug with enriched products |
