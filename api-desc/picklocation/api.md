# API Documentation - Pickup Location Feature

Admin prefix `/api/v1`. Public prefix `/api/v1/general`.

## 1. Admin: List Pickup Locations

**GET** `/api/v1/pickup-locations`

**Query:** `per_page` or `limit` (default 15), `search` (store_name LIKE), `active` (boolean string), `inactive` (boolean string)

Sorted by `display_order ASC`, then `id ASC`.

```json
{
    "data": {
        "data": [
            {
                "id": 1,
                "store_name": "Downtown Branch",
                "address": "123 Main St",
                "phone": "01000000001",
                "email": null,
                "latitude": null,
                "longitude": null,
                "working_hours": [
                    {"day": "Monday", "open": "09:00", "close": "21:00"}
                ],
                "status": true,
                "display_order": 1,
                "is_default": true,
                "created_at": "2026-07-20T10:00:00.000000Z"
            }
        ],
        "page": 1,
        "current_page": 1,
        "from": 1,
        "to": 15,
        "last_page": 1,
        "path": "https://example.com/api/v1/pickup-locations",
        "per_page": 15,
        "total": 1,
        "next_page_url": "",
        "prev_page_url": "",
        "last_page_url": "",
        "first_page_url": ""
    }
}
```

Note: Pagination metadata is manually extracted from the underlying ResourceCollection response, resulting in duplicate keys (`page` and `current_page` both present).

## 2. Admin: Create Pickup Location

**POST** `/api/v1/pickup-locations`

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `store_name` | string | Yes | max:255 |
| `address` | string | Yes | |
| `phone` | string | Yes | max:50 |
| `email` | string | No | email, max:255 |
| `latitude` | string | No | max:50 |
| `longitude` | string | No | max:50 |
| `working_hours` | array | No | |
| `working_hours.*.day` | string | With working_hours | |
| `working_hours.*.open` | string | With working_hours | |
| `working_hours.*.close` | string | With working_hours | |
| `status` | bool | No | in:1,0 |
| `display_order` | int | No | min:0 |
| `is_default` | bool | No | boolean |

**Default switching:** Setting `is_default: true` atomically clears the flag on every other location — exactly one default is guaranteed. Omit `is_default` (defaults to `false`).

## 3. Admin: Show Pickup Location

**GET** `/api/v1/pickup-locations/{id}`

Returns same structure as list item.

## 4. Admin: Update Pickup Location

**PUT** `/api/v1/pickup-locations/{id}`

All fields are optional (`sometimes`).

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `store_name` | string | No | max:255 |
| `address` | string | No | |
| `phone` | string | No | max:50 |
| `email` | string | No | nullable, email, max:255 |
| `latitude` | string | No | nullable, max:50 |
| `longitude` | string | No | nullable, max:50 |
| `working_hours` | array | No | nullable |
| `working_hours.*.day.ar` | string | With working_hours | required_with:working_hours |
| `working_hours.*.day.en` | string | With working_hours | required_with:working_hours |
| `working_hours.*.open` | string | With working_hours | required_with:working_hours |
| `working_hours.*.close` | string | With working_hours | required_with:working_hours |
| `status` | bool | No | in:1,0 |
| `display_order` | int | No | min:0 |
| `is_default` | bool | No | boolean |

**Note:** Update uses translatable `day.ar`/`day.en` keys (strings) instead of a flat `day` string used in Create.

**Default behavior:**
- Setting `is_default: true` on any location switches the default (others reset atomically).
- Updating other fields of the current default preserves `is_default`.
- Setting `is_default: false` on a location just clears it (no auto-promotion).
- Deleting the default promotes the next location by lowest `id` automatically.

## 5. Admin: Delete Pickup Location

**DELETE** `/api/v1/pickup-locations/{id}`

Uses SoftDeletes — record is not hard-removed. If the deleted location was the default, the remaining location with the lowest `id` is promoted to default.

## 6. Public: List Active Pickup Locations

**GET** `/api/v1/general/pickup-locations`

Returns only `status = true` locations. No auth required. Each item includes `is_default` so the frontend can pre-select the default branch.

## 7. Public: Show Pickup Location

**GET** `/api/v1/general/pickup-locations/{id}`

Returns 404 if inactive or soft-deleted. No auth required. Includes `is_default`.
