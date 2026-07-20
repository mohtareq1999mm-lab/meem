# Database - Pickup Location Feature

## Table: `pickup_locations`

| Column | Type | Constraints |
|--------|------|------------|
| `id` | bigint | PK, auto-increment |
| `store_name` | string | |
| `address` | text | |
| `phone` | string(50) | |
| `email` | string(255) | nullable |
| `latitude` | string(50) | nullable |
| `longitude` | string(50) | nullable |
| `working_hours` | json | nullable |
| `status` | boolean | default true |
| `display_order` | integer | default 0 |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp | nullable (SoftDeletes) |

## Related Table: `orders`

Columns appended by migration `2026_07_11_000004_add_pickup_location_snapshot_to_orders`:

| Column | Type | Description |
|--------|------|-------------|
| `pickup_location_id` | bigint | nullable FK |
| `pickup_location_name` | string | nullable snapshot |
| `pickup_location_address` | text | nullable snapshot |
| `pickup_location_phone` | string | nullable snapshot |
| `pickup_location_coordinates` | string | nullable snapshot (lat,lng) |

## Key Queries

| Use Case | Pattern |
|----------|---------|
| Admin list (ordered) | `SELECT * FROM pickup_locations ORDER BY display_order ASC, id ASC` |
| Admin list (search) | `WHERE store_name LIKE '%search%'` |
| Active filter | `WHERE status = 1` |
| Inactive filter | `WHERE status = 0` |
| Public list | `WHERE status = 1 AND deleted_at IS NULL ORDER BY display_order ASC, id ASC` |
| Public show | `WHERE id = ? AND status = 1 AND deleted_at IS NULL` |
| Soft delete | `UPDATE pickup_locations SET deleted_at = NOW() WHERE id = ?` |
