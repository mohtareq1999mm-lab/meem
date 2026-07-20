# Backend - Pickup Location Feature

## Controllers

### Admin: PickupLocationController (`packages/marvel/src/Http/Controllers/PickupLocationController.php`)

| Method | Permission | Description |
|--------|------------|-------------|
| `index` | VIEW_PICKUP_LOCATIONS | Paginated list (default 15), optional search/active/inactive filter, ordered by display_order → id |
| `store` | CREATE_PICKUP_LOCATION | Create with validated data |
| `show` | VIEW_PICKUP_LOCATIONS | Find or fail by ID |
| `update` | UPDATE_PICKUP_LOCATION | Find, update, return refreshed |
| `destroy` | DELETE_PICKUP_LOCATION | Find, soft delete |

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
| `deleted_at` | timestamp (nullable) | SoftDeletes |

**Scopes:** `active()` (status=true), `inactive()` (status=false), `ordered()` (display_order → id)

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
| `created_at` | `$this->created_at` |

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
