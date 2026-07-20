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

## 3. Admin: Show Pickup Location

**GET** `/api/v1/pickup-locations/{id}`

Returns same structure as list item.

## 4. Admin: Update Pickup Location

**PUT** `/api/v1/pickup-locations/{id}`

Same fields as store, all optional (`sometimes`).

## 5. Admin: Delete Pickup Location

**DELETE** `/api/v1/pickup-locations/{id}`

Uses SoftDeletes — record is not hard-removed.

## 6. Public: List Active Pickup Locations

**GET** `/api/v1/general/pickup-locations`

Returns only `status = true` locations. No auth required.

## 7. Public: Show Pickup Location

**GET** `/api/v1/general/pickup-locations/{id}`

Returns 404 if inactive or soft-deleted. No auth required.
