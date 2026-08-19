# Backend - Pickup Location Feature

## Controllers

### Admin: PickupLocationController (`packages/marvel/src/Http/Controllers/PickupLocationController.php`)

| Method | Permission | Description |
|--------|------------|-------------|
| `index` | VIEW_PICKUP_LOCATIONS | Paginated list (default 15), optional search/active/inactive filter, ordered by display_order → id |
| `store` | CREATE_PICKUP_LOCATION | Create with validated data; flush `PICKUP_LOCATIONS` cache tag |
| `show` | VIEW_PICKUP_LOCATIONS | Find or fail by ID |
| `update` | UPDATE_PICKUP_LOCATION | Find, update, return refreshed; flush `PICKUP_LOCATIONS` cache tag |
| `destroy` | DELETE_PICKUP_LOCATION | Find, soft delete; flush `PICKUP_LOCATIONS` cache tag |

**Note:** `index()` manually extracts pagination meta from ResourceCollection (duplicate keys `page`/`current_page`).

### Public: GeneralPickupLocationController (`app/Http/Controllers/Api/General/GeneralPickupLocationController.php`)

- `index`: returns only active ordered locations (no auth)
- `show`: returns active only; 404 if inactive

## Repository - `PickupLocationRepository`

Extends `Prettus\Repository\Eloquent\BaseRepository`. Uses `RequestCriteria` for search.

```php
protected $fieldSearchable = ['store_name' => 'like'];
```

## Model - `PickupLocation`

**Table:** `pickup_locations`

| Column | Type | Default |
|--------|------|---------|
| `store_name` | string | |
| `address` | text | |
| `phone` | string | |
| `email` | string (nullable) | |
| `latitude` | string (nullable) | |
| `longitude` | string (nullable) | |
| `working_hours` | json (nullable) | |
| `status` | boolean | true |
| `display_order` | integer | 0 |
| `is_default` | boolean | false |
| `deleted_at` | timestamp (nullable) | SoftDeletes |

**Scopes:** `active()` (status=true), `inactive()` (status=false), `ordered()` (display_order → id), `default()` (is_default=true)

**Model hooks (enforce exactly-one-default invariant):**
- `saving`: when `is_default` is set to `true` (dirty), a single atomic `UPDATE` clears the flag on all other rows (incl. soft-deleted) before the change persists.
- `deleted`: when the deleted location was the default, the remaining location with the lowest `id` is promoted to default.

**`is_default` cast:** boolean.

**Service method:** `PickupLocationService::getDefaultPickupLocation()` → `PickupLocation::default()->active()->first()` (active default, or `null`).

## Form Requests

### StorePickupLocationRequest

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `store_name` | string | Yes | max:255 |
| `address` | string | Yes | |
| `phone` | string | Yes | max:50 |
| `email` | string | No | nullable, email, max:255 |
| `latitude` | string | No | nullable, max:50 |
| `longitude` | string | No | nullable, max:50 |
| `working_hours` | array | No | nullable |
| `working_hours.*.day` | string | With working_hours | |
| `working_hours.*.open` | string | With working_hours | |
| `working_hours.*.close` | string | With working_hours | |
| `status` | bool | No | in:1,0 |
| `display_order` | int | No | min:0 |
| `is_default` | bool | No | sometimes, boolean |

### UpdatePickupLocationRequest

All fields are optional (`sometimes`). Additional behaviors vs Store:

| Field | Type | Rules | Difference from Store |
|-------|------|-------|----------------------|
| `store_name` | string | sometimes, max:255 | |
| `address` | string | sometimes | |
| `phone` | string | sometimes, max:50 | |
| `email` | string | nullable, email, max:255 | |
| `latitude` | string | nullable, max:50 | |
| `longitude` | string | nullable, max:50 | |
| `working_hours` | array | nullable | |
| `working_hours.*.day.ar` | string | required_with:working_hours | Translatable day name (Arabic) |
| `working_hours.*.day.en` | string | required_with:working_hours | Translatable day name (English) |
| `working_hours.*.open` | string | required_with:working_hours | |
| `working_hours.*.close` | string | required_with:working_hours | |
| `status` | bool | sometimes, in:1,0 | |
| `display_order` | int | sometimes, integer, min:0 | |
| `is_default` | bool | sometimes, boolean | |

## Resource - `PickupLocationResource`

| Field | Source |
|-------|--------|
| `id` | `$this->id` |
| `store_name` | `$this->store_name` |
| `address` | `$this->address` |
| `phone` | `$this->phone` |
| `email` | `$this->email` |
| `latitude` | `$this->latitude` |
| `longitude` | `$this->longitude` |
| `working_hours` | `$this->working_hours` (array) |
| `status` | `(bool) $this->status` |
| `display_order` | `$this->display_order` |
| `is_default` | `(bool) $this->is_default` |
| `created_at` | `$this->created_at` |

The public resource (`app/Http/Resources/PickupLocation/PickupLocationResource.php`) exposes the same fields minus `created_at`, including `is_default`.

## Permissions (4 Spatie permissions)

| Permission Slug | Used On |
|----------------|---------|
| `view-pickup-locations` | index, show |
| `create-pickup-location` | store |
| `update-pickup-location` | update |
| `delete-pickup-location` | destroy |

## Translations

Both EN and AR have all 3 keys present:

| Key | EN | AR |
|-----|----|-----|
| `MESSAGE.PICKUP_LOCATION_CREATED_SUCCESSFULLY` | Pickup location created successfully | تم إنشاء موقع الاستلام بنجاح |
| `MESSAGE.PICKUP_LOCATION_UPDATED_SUCCESSFULLY` | Pickup location updated successfully | تم تحديث موقع الاستلام بنجاح |
| `MESSAGE.PICKUP_LOCATION_DELETED_SUCCESSFULLY` | Pickup location deleted successfully | تم حذف موقع الاستلام بنجاح |
