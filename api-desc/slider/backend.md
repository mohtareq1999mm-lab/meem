# Slider Module — Backend Architecture

## Overview

The Slider module manages promotional slider/banner images on the platform. It follows a standard Controller → Repository → Model pattern with translatable titles, image uploads, product associations, soft deletes, and sortable reordering.

The module has import/export support for Excel bulk operations and a MediaCleanupObserver for cleaning up media files on force delete.

## Endpoints

### Admin API (`/api/v1/sliders`)

| Method | URL | Auth | Permission | Purpose |
|--------|-----|------|------------|---------|
| GET | `/api/v1/sliders` | `auth:sanctum` | `view-slider` | List sliders (paginated, filterable) |
| POST | `/api/v1/sliders` | `auth:sanctum` | `create-slider` | Create a new slider |
| GET | `/api/v1/sliders/{id}` | `auth:sanctum` | `view-slider` | Show slider by ID |
| PUT | `/api/v1/sliders/{id}` | `auth:sanctum` | `update-slider` | Update slider |
| DELETE | `/api/v1/sliders/{id}` | `auth:sanctum` | `delete-slider` | Soft delete slider |
| PATCH | `/api/v1/sliders/change-status` | `auth:sanctum` | `update-slider` | Toggle status |
| PUT | `/api/v1/sliders/reorder` | `auth:sanctum` | `update-slider` | Reorder sliders |

### Public API (`/api/v1/general/sliders`)

| Method | URL | Auth | Purpose |
|--------|-----|------|---------|
| GET | `/api/v1/general/sliders` | Public | List active sliders |
| GET | `/api/v1/general/sliders/{slug}` | Public | Get slider by slug with products |

## Route Definitions

### Admin Routes
**File:** `packages/marvel/src/Rest/Routes.php`

```
Line 164: Route::apiResource('sliders', SliderController::class);                              // Full CRUD
Line 165: Route::apiResource('sliders', SliderController::class);                              // (duplicate registration)
Line 201: Route::patch('sliders/change-status', [SliderController::class, 'changeStatus']);     // Toggle status
Line 202: Route::put('sliders/reorder', [SliderController::class, 'reorder']);                  // Reorder
Line 204: Route::apiResource('sliders', SliderController::class);                              // (third registration)
```

All admin routes are inside `auth:sanctum` + `email.verified` middleware group.

### Public Routes
**File:** `routes/api.php`

```
Line 50: Route::get('sliders', [SliderController::class, 'index']);                            // Prefix: /api/v1/general
Line 51: Route::get('sliders/{slug}', [SliderController::class, 'getSliderBySlug']);           // Prefix: /api/v1/general
```

## Middleware

### Admin Controller (`Marvel\Http\Controllers\SliderController`)

| Method | Middleware |
|--------|-----------|
| `index` | `permission:view-slider` (via constructor) |
| `show` | `permission:view-slider` (via constructor) |
| `store` | `permission:create-slider` (via constructor) |
| `update` | `permission:update-slider` (via constructor) |
| `changeStatus` | `permission:update-slider` (via constructor) |
| `reorder` | `permission:update-slider` (via constructor) |
| `destroy` | `permission:delete-slider` (via constructor) |

### Public Controller (`App\Http\Controllers\Api\General\SliderController`)

No middleware — fully public access.

## Controller Flow

### Admin Controller (`Marvel\Http\Controllers\SliderController`)
**File:** `packages/marvel/src/Http/Controllers/SliderController.php`

```
SliderController
│
├── index(Request)
│   ├── if active param → scopeActive()
│   ├── orderBy / sortedBy
│   └── paginate(limit) → SliderResource::collection()
│
├── store(SliderCreateRequest)
│   └── SliderRepository::createSlider($request)
│       ├── DB::transaction
│       ├── Slider::create($data)
│       ├── upload image_desktop → 'slider-image-desktop' collection
│       ├── upload image_mobile → 'slider-image-mobile' collection
│       └── sync products if provided
│
├── show($id)
│   ├── SliderRepository::findOrFail($id)
│   └── SliderResource::make()
│
├── update(SliderUpdateRequest, $id)
│   └── SliderRepository::updateSlider($request, $id)
│       ├── DB::transaction
│       ├── findOrFail($id)
│       ├── update slider
│       ├── updateSingleImage() if image_desktop provided
│       ├── updateSingleImage() if image_mobile provided
│       └── sync products if provided
│
├── changeStatus(Request)
│   └── SliderRepository::changeStatus($id)
│       ├── findOrFail($id)
│       ├── toggle status
│       └── return updated slider
│
├── reorder(Request)
│   └── SliderRepository::reorder($sliders)
│       └── setNewOrder() — Spatie Sortable
│
└── destroy($id)
    ├── findOrFail($id)
    └── $slider->delete()  → soft delete (sets deleted_at)
```

### Public Controller (`App\Http\Controllers\Api\General\SliderController`)
**File:** `app/Http/Controllers/Api/General/SliderController.php`

```
SliderController
│
├── index(Request)
│   ├── if slug query param → getSliderBySlug($slug)
│   └── SliderService::getSliders($request)
│       ├── active() scope
│       ├── optional date/ID filters
│       ├── orderBy id desc
│       └── limit(default 50)
│
└── getSliderBySlug($slug)
    └── SliderService::getSliderBySlug($slug)
        ├── active() + where slug
        ├── load products with channel filter
        ├── enrich with pricing
        └── 404 if not found
```

## Repository Methods

**File:** `packages/marvel/src/Database/Repositories/SliderRepository.php`

| Method | Description |
|--------|-------------|
| `getSliders(Request)` | Paginated list with active filter and ordering |
| `createSlider(Request)` | Creates slider in transaction with image uploads + product sync |
| `updateSlider(Request, $id)` | Updates slider in transaction with image replacement + product sync |
| `changeStatus($id)` | Toggles boolean status |
| `reorder(array $sliders)` | Reorders via Spatie Sortable `setNewOrder()` |

## Model Properties

**File:** `packages/marvel/src/Database/Models/Slider.php`

### Fillable
```php
protected $fillable = [
    'title', 'slug', 'order', 'status'
];
```

### Translatable
```php
public array $translatable = ['title'];
```

### Sortable
```php
public $sortable = [
    'order_column_name' => 'order',
    'sort_when_creating' => true,
];
```

### Soft Deletes
The model uses `Illuminate\Database\Eloquent\SoftDeletes`.

### Media
The model implements `HasMedia` and uses `InteractsWithMedia` trait.

### Model Events
| Event | Behavior |
|-------|----------|
| `saving` | Auto-generates `slug` from English title via `Str::slug()` |

### Relations

| Relation | Type | Pivot/FK |
|----------|------|----------|
| `products()` | BelongsToMany | `slider_product` (slider_id, product_id) |

### Scopes

| Scope | Description |
|-------|-------------|
| `active()` | `where('status', true)` |
| `search($field, $term, $locale)` | LIKE search on translatable fields |

## Service Layer

### SliderService (`app/Services/General/SliderService.php`)

| Method | Description |
|--------|-------------|
| `getSliders(Request)` | Active sliders with date/ID filters, ordered by id desc, limited |
| `getSliderBySlug($slug)` | Active slider by slug with products loaded + channel filter + pricing |

Uses `HasChannelFilter` trait for multi-channel product filtering.

## Resources

### Admin SliderResource (`packages/marvel/src/Http/Resources/SliderResource.php`)

| Field | Type | Behavior |
|-------|------|----------|
| id | int | Slider ID |
| title | string/object | On index: translated string. On show: full translations object |
| slug | string | Auto-generated slug |
| status | bool | Active/inactive |
| order | int | Sort order |
| image | object | `{ desktop: url, mobile: url }` |
| products | array | When loaded: id, name, slug, status, thumbnail |

### Public SliderResource (`app/Http/Resources/Slider/SliderResource.php`)

| Field | Type | Behavior |
|-------|------|----------|
| id | int | Slider ID |
| title | string | Translated string for current locale |
| slug | string | Auto-generated slug |
| status | bool | Active/inactive |
| image | object | `{ desktop: url, mobile: url }` |
| products | array | When loaded: ProductMiniResource collection |

## Observer

**File:** `app/Observers/MediaCleanupObserver.php`

Registered in `EventServiceProvider.php` for `Slider::class`.

| Event | Behavior |
|-------|----------|
| `deleting` | If model uses SoftDeletes → returns early (no cleanup on soft delete) |
| `forceDeleting` | Deletes all associated media records |

## Import / Export

### SlidersSheetImport (`packages/marvel/src/Imports/Sheets/SlidersSheetImport.php`)

Reads Excel sheet named `sliders`, groups rows by `product_sku`, and syncs slider slugs per product.

### SlidersSheetExport (`packages/marvel/src/Exports/Sheets/SlidersSheetExport.php`)

Exports sheet named `sliders` with columns `product_sku` and `slider_slug` for all products.

## Permissions

| Permission | Constant | Description |
|------------|----------|-------------|
| `view-slider` | `Permission::VIEW_SLIDER` | View slider list and details |
| `create-slider` | `Permission::CREATE_SLIDER` | Create new sliders |
| `update-slider` | `Permission::UPDATE_SLIDER` | Update, reorder, change status |
| `delete-slider` | `Permission::DELETE_SLIDER` | Delete sliders |

## Constants

| Constant | Translation Key |
|----------|-----------------|
| `SLIDER_CREATED_SUCCESSFULLY` | `MESSAGE.SLIDER_CREATED_SUCCESSFULLY` |
| `SLIDER_UPDATED_SUCCESSFULLY` | `MESSAGE.SLIDER_UPDATED_SUCCESSFULLY` |
| `SLIDER_DELETED_SUCCESSFULLY` | `MESSAGE.SLIDER_DELETED_SUCCESSFULLY` |
| `SLIDER_STATUS_CHANGED` | `MESSAGE.SLIDER_STATUS_CHANGED` |
| `SLIDERS_REORDERED_SUCCESSFULLY` | `MESSAGE.SLIDERS_REORDERED_SUCCESSFULLY` |

## Seeders

| File | Description |
|------|-------------|
| `database/seeders/SliderSeeder.php` | Seeds 10 sliders with bilingual titles and sample images |
| `database/seeders/SliderProductSeeder.php` | Attaches 1-3 sliders to every product |

## Complete Dependency Graph

```
SliderController (Admin)
├── SliderCreateRequest / SliderUpdateRequest (validation)
├── SliderRepository
│   ├── Slider (Model)
│   │   ├── HasTranslations (title)
│   │   ├── InteractsWithMedia (images)
│   │   ├── SoftDeletes (deleted_at)
│   │   ├── SortableTrait (order column)
│   │   └── BelongsToMany Products (slider_product pivot)
│   └── MediaManager (image upload)
└── SliderResource (response)

SliderController (Public)
├── SliderService
│   └── Slider::active()
└── SliderResource (public response)

MediaCleanupObserver → Cleans media on forceDelete
SlidersSheetImport → Bulk import slider-product associations
SlidersSheetExport → Bulk export slider-product associations
```
